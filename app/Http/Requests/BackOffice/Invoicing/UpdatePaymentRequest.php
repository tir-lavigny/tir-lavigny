<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Http\Requests\BackOffice\Invoicing\Concerns\ValidatesPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaymentRequest extends FormRequest
{
    use ValidatesPayment;

    public function authorize(): bool
    {
        return $this->user()?->canAny(['create payments', 'manage invoicing']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paymentRules();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePaymentAmount($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->preparePaymentForValidation();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->paymentMessages();
    }
}
