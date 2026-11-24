<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Models\CreditNoteSequence;
use App\Domains\Invoicing\Services\CreditNoteNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocates_sequential_credit_note_numbers_per_year(): void
    {
        $generator = app(CreditNoteNumberGenerator::class);

        $this->assertSame('CN-2027-0001', $generator->allocate(2027));
        $this->assertSame('CN-2027-0002', $generator->allocate(2027));
        $this->assertSame('CN-2028-0001', $generator->allocate(2028));
        $this->assertSame('CN-2027-0003', $generator->allocate(2027));
    }

    public function test_rejects_credit_note_sequence_overflow(): void
    {
        CreditNoteSequence::create(['year' => 2027, 'last_number' => 9999]);

        $this->expectException(\RuntimeException::class);

        app(CreditNoteNumberGenerator::class)->allocate(2027);
    }
}
