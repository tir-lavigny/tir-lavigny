<?php

namespace App\Rules\Register;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class NoOpenDayExists implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table('operating_days')
            ->where('season_id', $value)
            ->whereNull('closed_at')
            ->exists();

        if ($exists) {
            $fail('An operating day is already open for this season.');
        }
    }
}
