<?php

namespace Tests\Unit\Enums;

use App\Enums\SwissCanton;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SwissCantonTest extends TestCase
{
    #[Test]
    public function test_has_all_26_cantons(): void
    {
        $this->assertCount(26, SwissCanton::cases());
    }

    #[Test]
    public function test_validation_rule_contains_all_cantons(): void
    {
        $rule = SwissCanton::validationRule();

        $expected = implode(',', [
            'AG', 'AI', 'AR', 'BL', 'BS', 'BE', 'FR', 'GE', 'GL', 'GR',
            'JU', 'LU', 'NE', 'NW', 'OW', 'SG', 'SH', 'SO', 'SZ', 'TG',
            'TI', 'UR', 'VD', 'VS', 'ZG', 'ZH',
        ]);

        $this->assertEquals($expected, $rule);
    }

    #[Test]
    public function test_all_case_values_are_two_letter_strings(): void
    {
        foreach (SwissCanton::cases() as $canton) {
            $this->assertIsString($canton->value);
            $this->assertEquals(2, strlen($canton->value));
            $this->assertEquals(strtoupper($canton->value), $canton->value);
        }
    }

    #[Test]
    public function test_validation_rule_can_be_used_in_in_rule(): void
    {
        $rule = SwissCanton::validationRule();
        $validator = Validator::make(
            ['canton' => 'VD'],
            ['canton' => ['required', 'string', 'in:'.$rule]]
        );
        $this->assertTrue($validator->passes());

        $validator = Validator::make(
            ['canton' => 'XX'],
            ['canton' => ['required', 'string', 'in:'.$rule]]
        );
        $this->assertTrue($validator->fails());
    }
}
