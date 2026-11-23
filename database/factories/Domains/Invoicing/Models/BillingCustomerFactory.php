<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingCustomer>
 */
class BillingCustomerFactory extends Factory
{
    protected $model = BillingCustomer::class;

    public function definition(): array
    {
        return [
            'member_id' => null,
            'type' => BillingCustomerType::Individual,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'organization_name' => null,
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('####'),
            'city' => fake()->city(),
            'country' => 'CH',
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'preferred_invoice_delivery' => InvoiceDeliveryMethod::Email,
        ];
    }

    public function organization(): static
    {
        return $this->state(fn (): array => [
            'type' => BillingCustomerType::Organization,
            'first_name' => null,
            'last_name' => null,
            'organization_name' => fake()->company(),
        ]);
    }

    public function postal(): static
    {
        return $this->state(fn (): array => [
            'preferred_invoice_delivery' => InvoiceDeliveryMethod::Postal,
        ]);
    }

    public function forMember(Member $member): static
    {
        return $this->state(fn (): array => [
            'member_id' => $member->id,
            'type' => BillingCustomerType::Individual,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'organization_name' => null,
            'email' => $member->email,
            'phone' => $member->tel,
        ]);
    }
}
