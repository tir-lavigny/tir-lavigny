<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipFeeCampaign>
 */
class MembershipFeeCampaignFactory extends Factory
{
    protected $model = MembershipFeeCampaign::class;

    public function definition(): array
    {
        $year = (int) now()->format('Y');

        return [
            'year' => $year,
            'name' => "Cotisations {$year}",
            'issue_date' => now()->startOfYear()->format('Y-m-d'),
            'due_date' => now()->startOfYear()->addMonth()->format('Y-m-d'),
            'status' => MembershipFeeCampaignStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
