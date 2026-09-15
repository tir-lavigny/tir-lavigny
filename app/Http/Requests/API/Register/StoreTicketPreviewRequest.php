<?php

namespace App\Http\Requests\API\Register;

use App\Services\TicketValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create ticket');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'twint'])],
            'operating_day_id' => ['nullable', 'exists:operating_days,id'],
            'member_id' => ['nullable', 'exists:members,id,deleted_at,NULL'],
            'sold_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.custom_unit_price' => ['nullable', 'numeric'],
            'items.*.voucher_id' => ['nullable', 'integer', 'exists:vouchers,id,deleted_at,NULL'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            app(TicketValidationService::class)->validateItems(
                $this->input('items', []),
                $this->input('operating_day_id'),
                $this->input('member_id'),
                $validator
            );
        });
    }
}
