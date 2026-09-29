<?php

namespace App\Domain\Sales;

use App\Models\Merchant;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleExchange;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostSaleExchange
{
    public function __construct(private readonly PostSaleReturn $returns, private readonly FinalizeSale $sales) {}

    /**
     * @param  array<string,mixed>  $data
     * @return array{exchange: SaleExchange, replayed: bool}
     */
    public function handle(Merchant $merchant, Store $store, Register $register, Sale $originalSale, User $actor, string $idempotencyKey, array $data, ?string $approvalToken): array
    {
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $register, $originalSale, $actor, $idempotencyKey, $data, $approvalToken, $hash): array {
            $existing = SaleExchange::query()->where('merchant_id', $merchant->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof SaleExchange) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The exchange idempotency key was reused with different data.');
                }

                return ['exchange' => $existing->load(['saleReturn.lines.saleLine', 'saleReturn.tenders', 'newSale.lines', 'newSale.tenders']), 'replayed' => true];
            }
            $returnData = $data['return'];
            $returnKey = (string) $returnData['idempotency_key'];
            unset($returnData['idempotency_key']);
            $returnResult = $this->returns->handle($merchant, $store, $register, $originalSale, $actor, $returnKey, $returnData, $approvalToken);
            abort_unless($returnResult['return']->state === 'posted', 409, 'An exchange cannot post while an external refund action is pending.');

            $saleData = $data['new_sale'];
            $saleKey = (string) $saleData['idempotency_key'];
            unset($saleData['idempotency_key']);
            $saleResult = $this->sales->handle($merchant, $store, $register, $actor, $saleKey, $saleData);
            $difference = BigDecimal::of($saleResult['sale']->total)->minus($returnResult['return']->total);
            $direction = $difference->isPositive() ? 'customer_pays' : ($difference->isNegative() ? 'customer_refund' : 'even');
            $exchange = SaleExchange::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $register->id, 'sale_return_id' => $returnResult['return']->id, 'new_sale_id' => $saleResult['sale']->id,
                'created_by_user_id' => $actor->id, 'number' => 'EXC-'.mb_strtoupper(substr((string) Str::ulid(), -12)), 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash,
                'difference_amount' => (string) $difference->abs()->toScale(2), 'difference_direction' => $direction, 'posted_at' => now(),
            ]);

            return ['exchange' => $exchange->load(['saleReturn.lines.saleLine', 'saleReturn.tenders', 'newSale.lines', 'newSale.tenders']), 'replayed' => false];
        }, attempts: 3);
    }
}
