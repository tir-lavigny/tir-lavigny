<?php

namespace App\Http\Requests\BackOffice\Register;

use App\Rules\Register\DateNotDuplicate;
use App\Rules\Register\NoOpenDayExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreOperatingDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('open day');
    }

    public function rules(): array
    {
        return [
            'season_id' => [
                'required',
                'exists:seasons,id,deleted_at,NULL',
                new NoOpenDayExists,
                new DateNotDuplicate,
            ],
            'date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
        ];
    }
}
