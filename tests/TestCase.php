<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function toUtc(?string $localDatetime): ?string
    {
        if (! $localDatetime) {
            return null;
        }

        return Carbon::parse($localDatetime, 'Europe/Zurich')->utc()->toDateTimeString();
    }
}
