<?php

namespace App\Domain\CustomerDisplay;

use App\Models\Cart;
use App\Models\CartQuote;
use App\Models\CustomerDisplayPairing;
use App\Models\CustomerDisplaySnapshot;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerDisplayService
{
    /** @return array{pairing: CustomerDisplayPairing, code: string} */
    public function issue(Register $register, User $actor): array
    {
        abort_unless(($register->capabilities['customer_display'] ?? false) === true, 409, 'This register has no customer display capability enabled.');

        return DB::transaction(function () use ($register, $actor): array {
            CustomerDisplayPairing::query()->where('register_id', $register->id)->whereIn('status', ['pending', 'active'])->lockForUpdate()->get()->each(function (CustomerDisplayPairing $pairing): void {
                $pairing->forceFill(['status' => 'revoked', 'active_guard' => null, 'revoked_at' => now()])->save();
                CustomerDisplaySnapshot::query()->where('customer_display_pairing_id', $pairing->id)->update(['state' => 'idle', 'payload' => json_encode($this->idlePayload(), JSON_THROW_ON_ERROR), 'cart_id' => null, 'sale_id' => null, 'clear_at' => null, 'published_at' => now()]);
            });
            $code = mb_strtoupper(Str::random(4).'-'.Str::random(4));
            $pairing = CustomerDisplayPairing::query()->create(['merchant_id' => $register->merchant_id, 'store_id' => $register->store_id, 'register_id' => $register->id, 'created_by_user_id' => $actor->id, 'code_hash' => $this->codeHash($code), 'channel_id' => (string) Str::uuid(), 'status' => 'pending', 'active_guard' => 1, 'pairing_expires_at' => now()->addMinutes(10)]);
            $this->audit($pairing, $actor, 'customer_display.pairing_issued');

            return ['pairing' => $pairing, 'code' => $code];
        });
    }

    /** @return array{pairing: CustomerDisplayPairing, token: string} */
    public function consume(string $code, Register $register): array
    {
        return DB::transaction(function () use ($code, $register): array {
            $pairing = CustomerDisplayPairing::query()->where('code_hash', $this->codeHash(mb_strtoupper(trim($code))))->lockForUpdate()->first();
            abort_unless($pairing instanceof CustomerDisplayPairing && $pairing->register_id === $register->id, 404, 'The display pairing was not found for this register.');
            abort_unless($pairing->status === 'pending' && $pairing->pairing_expires_at->isFuture(), 409, 'The display pairing expired or was already used.');
            abort_unless(($register->capabilities['customer_display'] ?? false) === true, 409, 'Customer display capability is disabled.');
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $pairing->forceFill(['display_token_hash' => hash('sha256', $token), 'status' => 'active', 'paired_at' => now(), 'last_seen_at' => now(), 'session_expires_at' => now()->addHours(16)])->save();
            CustomerDisplaySnapshot::query()->create(['merchant_id' => $pairing->merchant_id, 'store_id' => $pairing->store_id, 'register_id' => $pairing->register_id, 'customer_display_pairing_id' => $pairing->id, 'sequence' => 1, 'state' => 'idle', 'payload' => $this->idlePayload(), 'published_at' => now()]);

            return ['pairing' => $pairing, 'token' => $token];
        });
    }

    public function revoke(CustomerDisplayPairing $pairing, User $actor): CustomerDisplayPairing
    {
        return DB::transaction(function () use ($pairing, $actor): CustomerDisplayPairing {
            $locked = CustomerDisplayPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            if ($locked->status !== 'revoked') {
                $locked->forceFill(['status' => 'revoked', 'active_guard' => null, 'display_token_hash' => null, 'revoked_at' => now()])->save();
                CustomerDisplaySnapshot::query()->where('customer_display_pairing_id', $locked->id)->update(['state' => 'idle', 'payload' => json_encode($this->idlePayload(), JSON_THROW_ON_ERROR), 'cart_id' => null, 'sale_id' => null, 'clear_at' => null, 'published_at' => now()]);
                $this->audit($locked, $actor, 'customer_display.pairing_revoked');
            }

            return $locked;
        });
    }

    public function publish(CustomerDisplayPairing $pairing, string $state, ?Cart $cart, ?Sale $sale): CustomerDisplaySnapshot
    {
        return DB::transaction(function () use ($pairing, $state, $cart, $sale): CustomerDisplaySnapshot {
            $locked = CustomerDisplayPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            $this->active($locked);
            if ($cart instanceof Cart) {
                abort_unless($cart->merchant_id === $locked->merchant_id && $cart->store_id === $locked->store_id && $cart->register_id === $locked->register_id, 404);
            }
            if ($sale instanceof Sale) {
                abort_unless($sale->merchant_id === $locked->merchant_id && $sale->store_id === $locked->store_id && $sale->register_id === $locked->register_id, 404);
            }
            $payload = match ($state) {
                'cart' => $this->cartPayload($cart),
                'completed' => $this->completedPayload($sale),
                'suspended' => ['schema_version' => 1, 'message' => 'This sale is paused.', 'lines' => [], 'totals' => null],
                'cancelled' => ['schema_version' => 1, 'message' => 'Sale cancelled.', 'lines' => [], 'totals' => null],
                default => $this->idlePayload(),
            };
            $snapshot = CustomerDisplaySnapshot::query()->where('customer_display_pairing_id', $locked->id)->lockForUpdate()->firstOrFail();
            $snapshot->forceFill(['sequence' => $snapshot->sequence + 1, 'state' => $state, 'payload' => $payload, 'cart_id' => $state === 'cart' ? $cart?->id : null, 'sale_id' => $state === 'completed' ? $sale?->id : null, 'clear_at' => $state === 'completed' ? now()->addSeconds(30) : null, 'published_at' => now()])->save();

            return $snapshot;
        });
    }

    public function read(CustomerDisplayPairing $pairing, string $token): CustomerDisplaySnapshot
    {
        return DB::transaction(function () use ($pairing, $token): CustomerDisplaySnapshot {
            $locked = CustomerDisplayPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            $this->active($locked);
            abort_unless($locked->display_token_hash !== null && hash_equals($locked->display_token_hash, hash('sha256', $token)), 401, 'The display credential is invalid.');
            $locked->forceFill(['last_seen_at' => now()])->save();
            $snapshot = CustomerDisplaySnapshot::query()->where('customer_display_pairing_id', $locked->id)->lockForUpdate()->firstOrFail();
            if ($snapshot->clear_at?->isPast()) {
                $snapshot->forceFill(['sequence' => $snapshot->sequence + 1, 'state' => 'idle', 'payload' => $this->idlePayload(), 'cart_id' => null, 'sale_id' => null, 'clear_at' => null, 'published_at' => now()])->save();
            }

            return $snapshot;
        });
    }

    private function active(CustomerDisplayPairing $pairing): void
    {
        abort_unless($pairing->status === 'active' && $pairing->session_expires_at?->isFuture(), 401, 'The display session expired or was revoked.');
    }

    /** @return array<string, mixed> */
    private function cartPayload(?Cart $cart): array
    {
        abort_unless($cart instanceof Cart, 422, 'A cart is required for the cart display state.');
        $cart->loadMissing(['lines.extras']);
        $quote = CartQuote::query()->where('cart_id', $cart->id)->where('cart_version', $cart->version)->latest('quote_version')->first();
        abort_unless($quote instanceof CartQuote && $quote->expires_at->isFuture(), 409, 'Quote the current cart before publishing it to the display.');
        $quotedLines = [];
        foreach ($quote->payload['lines'] ?? [] as $quotedLine) {
            if (is_array($quotedLine) && is_string($quotedLine['line_id'] ?? null)) {
                $quotedLines[$quotedLine['line_id']] = $quotedLine;
            }
        }
        $lines = [];
        foreach ($cart->lines as $line) {
            $price = $quotedLines[$line->public_id] ?? null;
            abort_unless(is_array($price), 409, 'The display quote does not match the cart.');
            $extras = [];
            foreach ($line->extras as $extra) {
                $extras[] = ['name' => $extra->option_name_snapshot];
            }
            $lines[] = ['name' => trim($line->product_name_snapshot.' '.($line->variant_name_snapshot ?? '')), 'quantity' => $line->quantity_uom, 'unit' => $line->unit_symbol_snapshot, 'unit_price' => $price['unit_price'], 'discount' => $price['discount'], 'gross' => $price['gross'], 'extras' => $extras];
        }

        return ['schema_version' => 1, 'message' => 'Your order', 'lines' => $lines, 'totals' => $quote->payload['totals'], 'currency' => 'PKR'];
    }

    /** @return array<string, mixed> */
    private function completedPayload(?Sale $sale): array
    {
        abort_unless($sale instanceof Sale, 422, 'A sale is required for the completed display state.');

        return ['schema_version' => 1, 'message' => 'Thank you. Payment complete.', 'lines' => [], 'totals' => ['gross' => $sale->total], 'currency' => 'PKR', 'receipt_number' => $sale->number];
    }

    /** @return array<string, mixed> */
    private function idlePayload(): array
    {
        return ['schema_version' => 1, 'message' => 'Welcome', 'lines' => [], 'totals' => null];
    }

    private function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function audit(CustomerDisplayPairing $pairing, User $actor, string $code): void
    {
        SecurityAuditEvent::query()->create(['merchant_id' => $pairing->merchant_id, 'store_id' => $pairing->store_id, 'actor_user_id' => $actor->id, 'event_code' => $code, 'outcome' => 'succeeded', 'metadata' => ['pairing_id' => $pairing->public_id, 'register_id' => $pairing->register_id], 'occurred_at' => now()]);
    }
}
