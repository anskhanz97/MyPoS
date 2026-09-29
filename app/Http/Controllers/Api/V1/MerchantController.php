<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Actions\CreateMerchantOnboarding;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);

        $merchants = Merchant::query()
            ->whereHas('memberships', fn ($query) => $query
                ->whereBelongsTo($user)
                ->where('status', MembershipStatus::Active))
            ->orderBy('name')
            ->get();

        return MerchantResource::collection($merchants);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMerchantRequest $request, CreateMerchantOnboarding $create): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $merchant = $create->handle($user, [
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
            'store_data_mode' => $request->string('store_data_mode')->toString(),
            'timezone' => $request->string('timezone')->toString(),
            'store_name' => $request->string('store_name')->toString(),
            'store_code' => $request->string('store_code')->toString(),
        ]);

        return (new MerchantResource($merchant))
            ->response()
            ->setStatusCode(201);
    }
}
