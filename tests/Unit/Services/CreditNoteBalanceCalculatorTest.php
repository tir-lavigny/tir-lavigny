<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteApplicationReversal;
use App\Domains\Invoicing\Models\CreditNoteRefund;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteBalanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_applied_refunded_and_reversed_amounts(): void
    {
        $creditNote = CreditNote::factory()->create(['total_cents' => 10000]);

        $application = CreditNoteApplication::factory()->for($creditNote, 'creditNote')->create([
            'amount_cents' => 3000,
        ]);
        CreditNoteApplicationReversal::factory()->for($application, 'application')->create([
            'amount_cents' => 500,
        ]);
        CreditNoteRefund::factory()->for($creditNote, 'creditNote')->create([
            'amount_cents' => 2000,
        ]);
        CreditNoteReversal::factory()->for($creditNote, 'creditNote')->create([
            'status' => CreditNoteReversalStatus::Issued,
            'total_cents' => 1000,
        ]);

        $calculator = app(CreditNoteBalanceCalculator::class);

        $this->assertSame(2500, $calculator->appliedCents($creditNote));
        $this->assertSame(2000, $calculator->refundedCents($creditNote));
        $this->assertSame(1000, $calculator->reversedCents($creditNote));
        $this->assertSame(4500, $calculator->availableCents($creditNote));
    }

    public function test_available_amount_never_becomes_negative(): void
    {
        $creditNote = CreditNote::factory()->create(['total_cents' => 1000]);

        CreditNoteApplication::factory()->for($creditNote, 'creditNote')->create([
            'amount_cents' => 800,
        ]);
        CreditNoteRefund::factory()->for($creditNote, 'creditNote')->create([
            'amount_cents' => 500,
        ]);

        $this->assertSame(0, app(CreditNoteBalanceCalculator::class)->availableCents($creditNote));
    }
}
