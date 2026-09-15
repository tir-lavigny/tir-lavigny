<?php

namespace App\Models;

use App\Events\EventChainCreated;
use App\LogsActivity;
use Carbon\Carbon;
use Database\Factories\EventChainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventChain extends Model
{
    /** @use HasFactory<EventChainFactory> */
    use HasFactory, LogsActivity;

    /**
     * Eloquent model events mapped to application events.
     * Dispatches EventChainCreated automatically after a new chain is persisted.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => EventChainCreated::class,
    ];

    /**
     * Valid recurrence types.
     */
    public const RECURRENCE_TYPES = ['daily', 'weekly', 'biweekly', 'monthly', 'custom'];

    /**
     * Fields that can be propagated from the chain to its events.
     * Used by EventChainUpdaterService to decide what to push down.
     */
    public const PROPAGATABLE_FIELDS = [
        'title',
        'location_id',
        'content_id',
        'start_time',
        'end_time',
        'custom_body',
    ];

    protected $fillable = [
        'title',
        'recurrence_type',
        'interval',
        'interval_unit',
        'day_of_week',
        'day_of_month',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location_id',
        'content_id',
        'custom_body',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'interval' => 'integer',
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    // ─── Recurrence engine ────────────────────────────────────────────────────

    /**
     * Returns all Carbon date instances (no time) for every occurrence
     * between start_date and end_date (inclusive) based on recurrence_type.
     *
     * @return Carbon[]
     */
    public function getOccurrenceDates(): array
    {
        $dates = [];
        $end = $this->end_date->copy()->startOfDay();

        switch ($this->recurrence_type) {
            case 'daily':
                $current = $this->start_date->copy()->startOfDay();
                while ($current->lte($end)) {
                    $dates[] = $current->copy();
                    $current->addDay();
                }
                break;

            case 'weekly':
                $current = $this->_firstOccurrenceFrom($this->start_date->copy(), $this->day_of_week);
                while ($current->lte($end)) {
                    $dates[] = $current->copy();
                    $current->addWeek();
                }
                break;

            case 'biweekly':
                $current = $this->_firstOccurrenceFrom($this->start_date->copy(), $this->day_of_week);
                while ($current->lte($end)) {
                    $dates[] = $current->copy();
                    $current->addWeeks(2);
                }
                break;

            case 'monthly':
                $current = $this->start_date->copy()->startOfDay();
                // Advance to the correct day_of_month in the first occurrence month
                $current->day = min($this->day_of_month, $current->daysInMonth);
                // If we overshot start_date, go back to start_date's month-day
                if ($current->lt($this->start_date->copy()->startOfDay())) {
                    $current->addMonthNoOverflow();
                    $current->day = min($this->day_of_month, $current->daysInMonth);
                }
                while ($current->lte($end)) {
                    $dates[] = $current->copy();
                    $current->addMonthNoOverflow();
                    $current->day = min($this->day_of_month, $current->daysInMonth);
                }
                break;

            case 'custom':
                $current = $this->start_date->copy()->startOfDay();
                $step = $this->interval ?? 1;
                $unit = $this->interval_unit === 'weeks' ? 'addWeeks' : 'addDays';
                while ($current->lte($end)) {
                    $dates[] = $current->copy();
                    $current->{$unit}($step);
                }
                break;
        }

        return $dates;
    }

    /**
     * Returns the first date >= $from whose day-of-week matches $targetDow
     * (Carbon 0=Monday … 6=Sunday convention matching tinyInteger storage).
     */
    private function _firstOccurrenceFrom(Carbon $from, int $targetDow): Carbon
    {
        $date = $from->copy()->startOfDay();
        // Carbon dayOfWeek: 0=Sunday, 1=Monday … 6=Saturday
        // We store day_of_week as 0=Monday … 6=Sunday (ISO week), so convert
        $carbonDow = ($targetDow + 1) % 7; // 0(Mon)->1, 6(Sun)->0
        while ($date->dayOfWeek !== $carbonDow) {
            $date->addDay();
        }

        return $date;
    }
}
