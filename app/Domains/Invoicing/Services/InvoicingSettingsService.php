<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Exceptions\InvoicingSettingsMissing;
use App\Domains\Invoicing\Models\InvoicingSettings;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class InvoicingSettingsService
{
    public function current(): ?InvoicingSettings
    {
        return InvoicingSettings::query()->first();
    }

    public function isConfigured(): bool
    {
        $settings = $this->current();

        return $settings !== null && $this->validationErrors($settings->toArray()) === [];
    }

    public function assertConfigured(): InvoicingSettings
    {
        $settings = $this->current();

        if ($settings === null || $this->validationErrors($settings->toArray()) !== []) {
            throw InvoicingSettingsMissing::make();
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function validationErrors(array $data): array
    {
        $errors = [];
        $required = [
            'creditor_name' => 'Le nom du créancier est obligatoire.',
            'creditor_address' => 'L’adresse du créancier est obligatoire.',
            'creditor_postal_code' => 'Le code postal du créancier est obligatoire.',
            'creditor_city' => 'La localité du créancier est obligatoire.',
            'creditor_country' => 'Le pays du créancier est obligatoire.',
            'creditor_iban' => 'L’IBAN du créancier est obligatoire.',
        ];

        foreach ($required as $field => $message) {
            if (blank($data[$field] ?? null)) {
                $errors[$field] = $message;
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $address = StructuredAddress::createWithStreet(
            (string) $data['creditor_name'],
            (string) $data['creditor_address'],
            null,
            (string) $data['creditor_postal_code'],
            (string) $data['creditor_city'],
            strtoupper((string) $data['creditor_country']),
        );

        if (! $address->isValid()) {
            $errors['creditor_address'] = $this->violationMessage($address->getViolations());
        }

        $creditorInformation = CreditorInformation::create($this->normalizeIban((string) $data['creditor_iban']));

        if (! $creditorInformation->isValid()) {
            $errors['creditor_iban'] = $this->violationMessage($creditorInformation->getViolations());
        } elseif ($creditorInformation->containsQrIban()) {
            $errors['creditor_iban'] = 'Un IBAN standard est requis pour les références SCOR, pas un QR-IBAN.';
        }

        return $errors;
    }

    /**
     * @return array{
     *     creditor_name: string,
     *     creditor_address: string,
     *     creditor_postal_code: string,
     *     creditor_city: string,
     *     creditor_country: string,
     *     creditor_iban: string
     * }
     */
    public function creditorSnapshot(): array
    {
        $settings = $this->assertConfigured();

        return [
            'creditor_name' => $settings->creditor_name,
            'creditor_address' => $settings->creditor_address,
            'creditor_postal_code' => $settings->creditor_postal_code,
            'creditor_city' => $settings->creditor_city,
            'creditor_country' => strtoupper($settings->creditor_country),
            'creditor_iban' => $this->normalizeIban($settings->creditor_iban),
        ];
    }

    public function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    private function violationMessage(ConstraintViolationListInterface $violations): string
    {
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages === [] ? 'Les données du créancier sont invalides.' : implode(' ', $messages);
    }
}
