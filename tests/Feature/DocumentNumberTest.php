<?php

namespace Tests\Feature;

use App\Domain\Shared\DocumentNumber;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_numbers_are_readable_unique_and_scoped_by_merchant_module_and_business_date(): void
    {
        $merchant = Merchant::factory()->create();
        $other = Merchant::factory()->create();
        $numbers = app(DocumentNumber::class);
        $this->assertSame('SALE-20261017-00001', $numbers->next($merchant->id, 'SALE', '2026-10-17'));
        $this->assertSame('SALE-20261017-00002', $numbers->next($merchant->id, 'SALE', '2026-10-17'));
        $this->assertSame('RET-20261017-00001', $numbers->next($merchant->id, 'RET', '2026-10-17'));
        $this->assertSame('SALE-20261018-00001', $numbers->next($merchant->id, 'SALE', '2026-10-18'));
        $this->assertSame('SALE-20261017-00001', $numbers->next($other->id, 'SALE', '2026-10-17'));
    }

    public function test_rolling_back_a_document_also_rolls_back_its_sequence_allocation(): void
    {
        $merchant = Merchant::factory()->create();
        $numbers = app(DocumentNumber::class);
        DB::beginTransaction();
        $this->assertSame('GRN-20261017-00001', $numbers->next($merchant->id, 'GRN', '2026-10-17'));
        DB::rollBack();
        $this->assertSame('GRN-20261017-00001', $numbers->next($merchant->id, 'GRN', '2026-10-17'));
    }
}
