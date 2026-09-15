<?php

namespace App\Http\Requests\API\Register;

use App\Rules\Register\DateNotDuplicate;
use App\Rules\Register\NoOpenDayExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('open day');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'season_id' => [
                'required',
                'exists:seasons,id,deleted_at,NULL',
                new NoOpenDayExists,
                new DateNotDuplicate,
            ],
        ];
    }
}
