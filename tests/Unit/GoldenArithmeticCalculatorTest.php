<?php

namespace Tests\Unit;

use App\Domain\Shared\Math\GoldenArithmeticCalculator;
use JsonException;
use Tests\TestCase;

class GoldenArithmeticCalculatorTest extends TestCase
{
    /** @throws JsonException */
    public function test_all_frozen_arithmetic_cases_match_the_shared_fixture(): void
    {
        $contents = file_get_contents(base_path('docs/contracts/fixtures/arithmetic-golden.json'));
        $this->assertNotFalse($contents);
        $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $calculator = new GoldenArithmeticCalculator;

        foreach ($fixture['cases'] as $case) {
            $this->assertSame($case['expected'], $calculator->calculate($case), $case['id']);
        }
    }
}
