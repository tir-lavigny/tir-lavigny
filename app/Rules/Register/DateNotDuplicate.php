<?php

namespace App\Rules\Register;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class DateNotDuplicate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();
        $seasonId = $request->input('season_id');

        $dt = Carbon::now()->setTime(0, 0);

        if (! $seasonId) {
            return;
        }

        $exists = DB::table('operating_days')
            ->where('season_id', $seasonId)
            ->whereDate('date', $dt)
            ->exists();

        if ($exists) {
            $fail('An operating day with this date already exists for this season.');
        }
    }
}
