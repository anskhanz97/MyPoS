<?php

namespace Tests\Unit;

use App\Domain\Pricing\DiscountAllocator;
use App\Domain\Shared\Math\GoldenArithmeticCalculator;
use Brick\Math\BigDecimal;
use Tests\TestCase;

class PromotionGoldenFixtureTest extends TestCase
{
    public function test_promotion_fixture_conserves_cart_and_partial_return_allocations(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('docs/contracts/fixtures/promotion-golden.json')), true, 512, JSON_THROW_ON_ERROR);
        $cases = collect($fixture['cases'])->keyBy('id');
        $cart = $cases->get('P004');
        $allocated = (new DiscountAllocator)->allocate(BigDecimal::of($cart['expected_sum']), [
            ['line_id' => 'a', 'sequence' => 1, 'weight' => BigDecimal::one()],
            ['line_id' => 'b', 'sequence' => 2, 'weight' => BigDecimal::one()],
            ['line_id' => 'c', 'sequence' => 3, 'weight' => BigDecimal::one()],
        ]);
        $this->assertSame($cart['line_allocations'], array_map(static fn (BigDecimal $amount): string => (string) $amount, array_values($allocated)));

        $partial = $cases->get('P005');
        $result = (new GoldenArithmeticCalculator)->calculate(['operation' => 'cumulative_partial_return', 'input' => ['original_quantity' => $partial['original_quantity'], 'original_refundable' => $partial['original_refundable'], 'return_quantities_in_order' => $partial['return_quantities']]]);
        $this->assertSame($partial['refunds'], $result['refunds_in_order']);
    }
}
