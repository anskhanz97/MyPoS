<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Pricing\PricingCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculatePricingQuoteRequest;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PricingQuoteController extends Controller
{
    public function __invoke(CalculatePricingQuoteRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, PricingCalculator $calculator): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $data = $request->validated();
        $hasManual = ((float) ($data['manual_cart_discount'] ?? 0)) > 0;
        $lines = $data['lines'] ?? [];
        abort_unless(is_array($lines), 422);
        foreach ($lines as $line) {
            if (is_array($line) && ((float) ($line['manual_discount'] ?? 0)) > 0) {
                $hasManual = true;
                break;
            }
        }
        if ($hasManual) {
            $authorizer->authorize($user, $merchant, Permission::DiscountOverride, $store);
        }

        return response()->json(['data' => $calculator->quote($merchant, $store, $data)]);
    }
}
