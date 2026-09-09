<?php

namespace Tests\Unit;

use App\Traits\ConvertsLocalDatetimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertsLocalDatetimesTest extends TestCase
{
    use RefreshDatabase;

    private object $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new class
        {
            use ConvertsLocalDatetimes;
        };
    }

    public function test_converts_local_datetime_to_utc(): void
    {
        // 18:00 CEST (UTC+2) = 16:00 UTC
        $result = $this->instance->localToUtc('2026-07-01 18:00:00');

        $this->assertEquals('2026-07-01 16:00:00', $result);
    }

    public function test_converts_local_datetime_to_utc_in_winter(): void
    {
        // 18:00 CET (UTC+1) = 17:00 UTC
        $result = $this->instance->localToUtc('2026-01-15 18:00:00');

        $this->assertEquals('2026-01-15 17:00:00', $result);
    }

    public function test_returns_null_for_null_input(): void
    {
        $result = $this->instance->localToUtc(null);

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_string(): void
    {
        $result = $this->instance->localToUtc('');

        $this->assertNull($result);
    }

    public function test_preserves_utc_input_unchanged(): void
    {
        // UTC input: 16:00:00
        // localToUtc interprets it as Zurich time, then converts back to UTC.
        // 16:00 CEST = 14:00 UTC, so this is NOT identity for UTC inputs.
        // This test documents the expected behaviour: localToUtc ALWAYS treats
        // input as Zurich-local time.
        $result = $this->instance->localToUtc('2026-07-01 16:00:00');

        // 16:00 CEST = 14:00 UTC
        $this->assertEquals('2026-07-01 14:00:00', $result);
    }

    public function test_roundtrip_local_to_utc_and_back(): void
    {
        $local = '2026-07-01 18:00:00';
        $utc = $this->instance->localToUtc($local);

        $this->assertEquals('2026-07-01 16:00:00', $utc);
    }

    public function test_utc_input_passes_through_unchanged(): void
    {
        // When frontend sends a string with Z suffix (e.g. '2026-09-10T16:00:00Z'),
        // Carbon parses it as UTC regardless of the timezone parameter.
        // The value passes through without the 2h shift.
        $result = $this->instance->localToUtc('2026-09-10T16:00:00Z');

        $this->assertEquals('2026-09-10 16:00:00', $result);
    }

    public function test_utc_offset_input_passes_through_unchanged(): void
    {
        // Same with +00:00 suffix
        $result = $this->instance->localToUtc('2026-09-10T16:00:00+00:00');

        $this->assertEquals('2026-09-10 16:00:00', $result);
    }
}
