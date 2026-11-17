<?php

namespace Tests\Unit\Support;

use App\Support\Avs;
use Tests\TestCase;

class AvsTest extends TestCase
{
    public function test_accepts_formatted_avs(): void
    {
        $this->assertTrue(Avs::looksLike('756.1234.5678.90'));
    }

    public function test_accepts_thirteen_digits(): void
    {
        $this->assertTrue(Avs::looksLike('7561234567890'));
        $this->assertTrue(Avs::looksLike('756 1234 5678 90'));
    }

    public function test_rejects_non_avs_values(): void
    {
        $this->assertFalse(Avs::looksLike(''));
        $this->assertFalse(Avs::looksLike('hello'));
        $this->assertFalse(Avs::looksLike('1234'));
        $this->assertFalse(Avs::looksLike('eyJpdiI6IkFCQyIsInZhbHVlIjoiZGVmIn0='));
    }
}
