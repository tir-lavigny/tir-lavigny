<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\InvoicingSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicingSettings>
 */
class InvoicingSettingsFactory extends Factory
{
    protected $model = InvoicingSettings::class;

    public function definition(): array
    {
        return [
            'creditor_name' => 'Société de Tir à Lavigny',
            'creditor_address' => 'Rue du Stand 1',
            'creditor_postal_code' => '1175',
            'creditor_city' => 'Lavigny',
            'creditor_country' => 'CH',
            'creditor_iban' => 'CH9300762011623852957',
            'updated_by' => null,
        ];
    }
}
