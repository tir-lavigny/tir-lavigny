<?php

namespace Tests\Unit\Rules;

use App\Models\Member;
use App\Rules\UniqueMemberAvs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueMemberAvsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_duplicate_despite_formatting(): void
    {
        Member::factory()->create(['avs' => '756.1234.5678.90']);

        $this->assertFalse($this->passes('756 1234 5678 90'));
    }

    public function test_fails_when_avs_belongs_to_another_member(): void
    {
        Member::factory()->create(['avs' => '756.1234.5678.90']);

        $this->assertFalse($this->passes('756.1234.5678.90'));
        $this->assertSame('Ce numéro AVS est déjà utilisé par un autre membre.', $this->message);
    }

    public function test_passes_when_avs_belongs_to_ignored_member(): void
    {
        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);

        $this->assertTrue($this->passes('756.1234.5678.90', $member->id));
    }

    public function test_passes_for_empty_value(): void
    {
        $this->assertTrue($this->passes(null));
        $this->assertTrue($this->passes(''));
    }

    private string $message = '';

    private function passes(mixed $value, ?int $ignoreMemberId = null): bool
    {
        $failed = false;
        $this->message = '';

        (new UniqueMemberAvs($ignoreMemberId))->validate('avs', $value, function (string $message) use (&$failed): void {
            $failed = true;
            $this->message = $message;
        });

        return ! $failed;
    }
}
