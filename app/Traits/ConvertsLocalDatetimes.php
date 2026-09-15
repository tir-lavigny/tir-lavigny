<?php

namespace App\Traits;

use Carbon\Carbon;

trait ConvertsLocalDatetimes
{
    public function localToUtc(?string $datetime, string $timezone = 'Europe/Zurich'): ?Carbon
    {
        if (! $datetime) {
            return null;
        }

        return Carbon::parse($datetime, $timezone)->utc();
    }
}
