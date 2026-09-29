<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Entitlement;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::query()->with('entitlements')->orderBy('name')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = DB::transaction(function () use ($request): Plan {
            $plan = Plan::query()->create($request->safe()->only(['code', 'name', 'billing_period_days', 'grace_days', 'price']) + ['currency' => 'PKR']);
            foreach ($request->array('entitlements') as $item) {
                Entitlement::query()->create(['plan_id' => $plan->id] + $item);
            }

            return $plan->load('entitlements');
        });

        return (new PlanResource($plan))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Plan $plan): PlanResource
    {
        return new PlanResource($plan->load('entitlements'));
    }
}
