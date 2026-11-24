<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Exceptions\InvoiceNumberSequenceExhausted;
use App\Domains\Invoicing\Models\InvoiceSequence;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocates_sequential_numbers_per_year(): void
    {
        $generator = app(InvoiceNumberGenerator::class);

        $this->assertSame('2027-0001', $generator->allocate(2027));
        $this->assertSame('2027-0002', $generator->allocate(2027));
        $this->assertSame('2028-0001', $generator->allocate(2028));
        $this->assertSame('2027-0003', $generator->allocate(2027));
    }

    public function test_rejects_sequence_overflow(): void
    {
        InvoiceSequence::create(['year' => 2027, 'last_number' => 9999]);

        $this->expectException(InvoiceNumberSequenceExhausted::class);

        app(InvoiceNumberGenerator::class)->allocate(2027);
    }
}
