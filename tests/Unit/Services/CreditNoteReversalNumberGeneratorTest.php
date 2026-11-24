<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Models\CreditNoteReversalSequence;
use App\Domains\Invoicing\Services\CreditNoteReversalNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteReversalNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocates_sequential_reversal_numbers_per_year(): void
    {
        $generator = app(CreditNoteReversalNumberGenerator::class);

        $this->assertSame('CNR-2027-0001', $generator->allocate(2027));
        $this->assertSame('CNR-2027-0002', $generator->allocate(2027));
        $this->assertSame('CNR-2028-0001', $generator->allocate(2028));
    }

    public function test_rejects_reversal_sequence_overflow(): void
    {
        CreditNoteReversalSequence::create(['year' => 2027, 'last_number' => 9999]);

        $this->expectException(\RuntimeException::class);

        app(CreditNoteReversalNumberGenerator::class)->allocate(2027);
    }
}
