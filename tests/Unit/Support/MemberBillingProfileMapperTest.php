<?php

namespace Tests\Unit\Support;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Support\MemberBillingProfileMapper;
use App\Models\Member;
use Tests\TestCase;

class MemberBillingProfileMapperTest extends TestCase
{
    public function test_maps_member_fields_to_billing_customer_attributes(): void
    {
        $member = Member::factory()->make([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'street' => 'Rue du Lac 4',
            'npa' => '1003',
            'city' => 'Lausanne',
            'country' => 'Suisse',
            'email' => 'alice@example.test',
            'tel' => '+41 21 000 00 00',
        ]);

        $attributes = (new MemberBillingProfileMapper)->map($member);

        $this->assertSame(BillingCustomerType::Individual, $attributes['type']);
        $this->assertSame('Alice', $attributes['first_name']);
        $this->assertSame('Martin', $attributes['last_name']);
        $this->assertSame('Rue du Lac 4', $attributes['address']);
        $this->assertSame('1003', $attributes['postal_code']);
        $this->assertSame('Lausanne', $attributes['city']);
        $this->assertSame('CH', $attributes['country']);
        $this->assertSame('alice@example.test', $attributes['email']);
        $this->assertSame('+41 21 000 00 00', $attributes['phone']);
        $this->assertSame(InvoiceDeliveryMethod::Email, $attributes['preferred_invoice_delivery']);
    }

    public function test_defaults_to_postal_delivery_when_member_has_no_email(): void
    {
        $member = Member::factory()->make(['email' => null]);

        $attributes = (new MemberBillingProfileMapper)->map($member);

        $this->assertSame(InvoiceDeliveryMethod::Postal, $attributes['preferred_invoice_delivery']);
    }
}
