<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Loyalty\PublicLoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLoyaltyController extends Controller
{
    public function show(Request $request, string $programSlug, PublicLoyaltyService $public): JsonResponse
    {
        $program = $public->program($programSlug);

        return response()->json(['data' => $this->programData($program)]);
    }

    public function join(Request $request, string $programSlug, PublicLoyaltyService $public): JsonResponse
    {
        $program = $public->program($programSlug);
        $data = $request->validate(['phone' => ['required', 'string', 'max:32'], 'display_name' => ['nullable', 'string', 'max:160'], 'store_id' => ['nullable', 'uuid'], 'marketing_consent' => ['sometimes', 'boolean']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[0-9a-f-]{36}$/i', $key) === 1, 422, 'A UUID Idempotency-Key header is required.');
        $result = $public->join($program, $key, $data);
        $result['member']->load('pointBalance');
        $public->audit($program, 'join', $result['already_member'] ? 'existing' : 'created', $data['phone'], (string) $request->ip(), $request->userAgent());

        return response()->json(['data' => $public->publicMember($program, $result['member']), 'already_member' => $result['already_member'], 'replayed' => $result['replayed']], $result['already_member'] || $result['replayed'] ? 200 : 201);
    }

    public function lookup(Request $request, string $programSlug, PublicLoyaltyService $public): JsonResponse
    {
        $program = $public->program($programSlug);
        $data = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $member = $public->lookup($program, $data['phone']);
        $public->audit($program, 'lookup', $member === null ? 'not_found' : 'found', $data['phone'], (string) $request->ip(), $request->userAgent());
        abort_unless($member !== null, 404, 'No member was found for this programme.');

        return response()->json(['data' => $public->publicMember($program, $member)]);
    }

    /** @return array<string,mixed> */
    private function programData(LoyaltyProgram $program): array
    {
        return ['slug' => $program->slug, 'name' => $program->name, 'rewards' => $program->rewards->map(fn ($reward) => ['name' => $reward->name, 'points_required' => $reward->points_required])->all(), 'notice' => 'Phone lookup shows limited loyalty information and does not verify identity.'];
    }
}
