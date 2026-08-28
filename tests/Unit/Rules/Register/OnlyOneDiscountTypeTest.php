<?php

namespace Tests\Unit\Rules\Register;

use App\Rules\Register\OnlyOneDiscountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class OnlyOneDiscountTypeTest extends TestCase
{
    use RefreshDatabase;

    private OnlyOneDiscountType $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new OnlyOneDiscountType;
    }

    public function test_passes_when_only_percent_provided(): void
    {
        Request::macro('testRequest', fn () => Request::create('/', 'POST', [
            'discount_percent' => 10,
        ]));

        $request = Request::create('/', 'POST', [
            'discount_percent' => 10,
        ]);

        $this->app['request'] = $request;

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('discount_percent', 10, $fail);
        $this->assertTrue(true);
    }

    public function test_passes_when_only_fixed_provided(): void
    {
        $request = Request::create('/', 'POST', [
            'discount_fixed' => 5.00,
        ]);

        $this->app['request'] = $request;

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('discount_fixed', 5.00, $fail);
        $this->assertTrue(true);
    }

    public function test_fails_when_both_provided(): void
    {
        $request = Request::create('/', 'POST', [
            'discount_percent' => 10,
            'discount_fixed' => 5.00,
        ]);

        $this->app['request'] = $request;

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('Only one of', $msg);
        };

        $this->rule->validate('discount_percent', 10, $fail);
        $this->assertTrue($failed, 'Validation should fail when both discount types provided');
    }

    public function test_passes_when_neither_provided(): void
    {
        $request = Request::create('/', 'POST', []);

        $this->app['request'] = $request;

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('discount_percent', null, $fail);
        $this->assertTrue(true);
    }
}
