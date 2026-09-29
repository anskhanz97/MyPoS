<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Notifications\NotificationService;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Models\InAppAlert;
use App\Models\InAppAlertRead;
use App\Models\Merchant;
use App\Models\MerchantEmailSetting;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\ReceiptSnapshot;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function emailReceipt(Request $request, Merchant $merchant, ReceiptSnapshot $receipt, RoleAuthorizer $auth, NotificationService $service): JsonResponse
    {
        $user = $this->user($request);
        $store = Store::query()->findOrFail($receipt->store_id);
        $auth->authorize($user, $merchant, Permission::SaleView, $store);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:320']]);

        return response()->json(['data' => $service->receipt($user, $merchant, $receipt, $data['email'])], 202);
    }

    public function messageReceipt(Request $request, Merchant $merchant, ReceiptSnapshot $receipt, RoleAuthorizer $auth, NotificationService $service): JsonResponse
    {
        $user = $this->user($request);
        $store = Store::query()->findOrFail($receipt->store_id);
        $auth->authorize($user, $merchant, Permission::SaleView, $store);
        $data = $request->validate(['channel' => ['required', 'in:sms,whatsapp'], 'phone' => ['required', 'string', 'max:30'], 'consent_confirmed' => ['accepted'], 'consent_source' => ['required', 'in:customer_request,checkout_prompt'], 'evidence_reference' => ['nullable', 'string', 'max:160']]);

        return response()->json(['data' => $service->receiptMessage($user, $merchant, $receipt, $data['channel'], $data['phone'], $data['consent_source'], $data['evidence_reference'] ?? null)], 202);
    }

    public function optOut(Request $request, Merchant $merchant, TenantAccess $access, NotificationService $service): JsonResponse
    {
        $user = $this->user($request);
        $access->membershipOrNotFound($user, $merchant);
        $data = $request->validate(['channel' => ['required', 'in:sms,whatsapp'], 'phone' => ['required', 'string', 'max:30']]);

        return response()->json(['data' => $service->optOut($user, $merchant, $data['channel'], $data['phone'])]);
    }

    public function delivery(Request $request, Merchant $merchant, NotificationDelivery $notificationDelivery, TenantAccess $access): JsonResponse
    {
        $user = $this->user($request);
        $membership = $access->membershipOrNotFound($user, $merchant);
        abort_unless($notificationDelivery->merchant_id === $merchant->id && ($notificationDelivery->requested_by_user_id === $user->id || $membership->role === MembershipRole::Owner), 404);

        return response()->json(['data' => $notificationDelivery->load('attempts')]);
    }

    public function settings(Request $request, Merchant $merchant, TenantAccess $access): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        $setting = MerchantEmailSetting::query()->firstOrCreate(['merchant_id' => $merchant->id], ['enabled' => false, 'provider_label' => 'configured mail transport', 'from_address' => config('mail.from.address'), 'from_name' => config('mail.from.name')]);

        return response()->json(['data' => $setting->only(['enabled', 'provider_label', 'from_address', 'from_name'])]);
    }

    public function updateSettings(Request $request, Merchant $merchant, TenantAccess $access): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'from_address' => ['required', 'email:rfc', 'max:320'], 'from_name' => ['required', 'string', 'max:120']]);
        $setting = MerchantEmailSetting::query()->updateOrCreate(['merchant_id' => $merchant->id], $data + ['provider_label' => 'configured mail transport']);

        return response()->json(['data' => $setting->only(['enabled', 'provider_label', 'from_address', 'from_name'])]);
    }

    public function testEmail(Request $request, Merchant $merchant, TenantAccess $access, NotificationService $service): JsonResponse
    {
        $user = $this->user($request);
        $access->ownerOrForbidden($user, $merchant);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:320']]);

        return response()->json(['data' => $service->testEmail($user, $merchant, $data['email'])], 202);
    }

    public function preferences(Request $request, Merchant $merchant, TenantAccess $access): JsonResponse
    {
        $user = $this->user($request);
        $access->membershipOrNotFound($user, $merchant);
        $data = $request->validate(['in_app_enabled' => ['required', 'boolean'], 'operational_email_enabled' => ['required', 'boolean']]);
        $preference = NotificationPreference::query()->updateOrCreate(['merchant_id' => $merchant->id, 'user_id' => $user->id], $data);

        return response()->json(['data' => $preference]);
    }

    public function alerts(Request $request, Merchant $merchant, TenantAccess $access): JsonResponse
    {
        $user = $this->user($request);
        $membership = $access->membershipOrNotFound($user, $merchant);
        $data = $request->validate(['store_id' => ['nullable', 'string'], 'unread_only' => ['nullable', 'boolean']]);
        $store = isset($data['store_id']) ? (new Store)->resolveRouteBinding($data['store_id']) : null;
        abort_unless($store === null || $store instanceof Store, 404);
        if ($store instanceof Store) {
            $access->assignedStoreOrNotFound($user, $merchant, $store);
        }
        $roles = match ($membership->role) {
            MembershipRole::Owner => ['owner', 'manager', 'all'],MembershipRole::Manager => ['manager', 'all'],MembershipRole::Cashier => ['all']
        };
        $alerts = InAppAlert::query()->where('merchant_id', $merchant->id)->whereNull('resolved_at')->whereIn('audience_role', $roles)->when($store instanceof Store, fn (Builder $q) => $q->where(fn (Builder $s) => $s->whereNull('store_id')->orWhere('store_id', $store->id)))->withExists(['reads as is_read' => fn (Builder $q) => $q->where('user_id', $user->id)])->when((bool) ($data['unread_only'] ?? false), fn (Builder $q) => $q->whereDoesntHave('reads', fn (Builder $r) => $r->where('user_id', $user->id)))->latest()->limit(100)->get();

        return response()->json(['data' => $alerts]);
    }

    public function read(Request $request, Merchant $merchant, InAppAlert $inAppAlert, TenantAccess $access): JsonResponse
    {
        $user = $this->user($request);
        $membership = $access->membershipOrNotFound($user, $merchant);
        abort_unless($inAppAlert->merchant_id === $merchant->id, 404);
        if ($inAppAlert->store_id !== null) {
            $access->assignedStoreOrNotFound($user, $merchant, Store::query()->findOrFail($inAppAlert->store_id));
        }
        $allowed = $membership->role === MembershipRole::Owner || $inAppAlert->audience_role === 'all' || ($membership->role === MembershipRole::Manager && $inAppAlert->audience_role === 'manager');
        abort_unless($allowed, 404);
        InAppAlertRead::query()->updateOrCreate(['in_app_alert_id' => $inAppAlert->id, 'user_id' => $user->id], ['read_at' => now()]);

        return response()->json(['data' => ['read' => true]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
