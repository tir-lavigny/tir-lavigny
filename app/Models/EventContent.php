<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\EventContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventContent extends Model
{
    /** @use HasFactory<EventContentFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'event_id',
        'body',
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
