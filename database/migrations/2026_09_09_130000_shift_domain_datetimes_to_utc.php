<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->shiftToUtc('events', 'start_datetime');
        $this->shiftToUtc('events', 'end_datetime');
        $this->shiftToUtc('operating_days', 'started_at');
        $this->shiftToUtc('operating_days', 'closed_at');
        $this->shiftToUtc('tickets', 'sold_at');
        $this->shiftToUtc('member_registrations', 'form_submitted_at');
        $this->shiftToUtc('member_registrations', 'failed_at');
    }

    public function down(): void
    {
        $this->shiftToLocal('events', 'start_datetime');
        $this->shiftToLocal('events', 'end_datetime');
        $this->shiftToLocal('operating_days', 'started_at');
        $this->shiftToLocal('operating_days', 'closed_at');
        $this->shiftToLocal('tickets', 'sold_at');
        $this->shiftToLocal('member_registrations', 'form_submitted_at');
        $this->shiftToLocal('member_registrations', 'failed_at');
    }

    private function shiftToUtc(string $table, string $column): void
    {
        DB::statement("
            UPDATE {$table}
            SET {$column} = CASE
                WHEN {$column} IS NOT NULL AND {$this->cestCondition($column)}
                THEN {$this->dateSub($column, 2)}
                ELSE {$this->dateSub($column, 1)}
            END
            WHERE {$column} IS NOT NULL
        ");
    }

    private function shiftToLocal(string $table, string $column): void
    {
        DB::statement("
            UPDATE {$table}
            SET {$column} = CASE
                WHEN {$column} IS NOT NULL AND {$this->cestCondition($column)}
                THEN {$this->dateAdd($column, 2)}
                ELSE {$this->dateAdd($column, 1)}
            END
            WHERE {$column} IS NOT NULL
        ");
    }

    private function cestCondition(string $column): string
    {
        if ($this->isSQLite()) {
            return "(
                (CAST(strftime('%m', {$column}) AS INTEGER) >= 4 AND CAST(strftime('%m', {$column}) AS INTEGER) <= 9)
                OR (CAST(strftime('%m', {$column}) AS INTEGER) = 3 AND CAST(strftime('%d', {$column}) AS INTEGER) >= 29)
                OR (CAST(strftime('%m', {$column}) AS INTEGER) = 10 AND CAST(strftime('%d', {$column}) AS INTEGER) < 25)
            )";
        }

        return "(
            (MONTH({$column}) >= 4 AND MONTH({$column}) <= 9)
            OR (MONTH({$column}) = 3 AND DAY({$column}) >= 29)
            OR (MONTH({$column}) = 10 AND DAY({$column}) < 25)
        )";
    }

    private function dateSub(string $column, int $hours): string
    {
        return $this->isSQLite()
            ? "datetime({$column}, '-{$hours} hours')"
            : "DATE_SUB({$column}, INTERVAL {$hours} HOUR)";
    }

    private function dateAdd(string $column, int $hours): string
    {
        return $this->isSQLite()
            ? "datetime({$column}, '+{$hours} hours')"
            : "DATE_ADD({$column}, INTERVAL {$hours} HOUR)";
    }

    private function isSQLite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
};
