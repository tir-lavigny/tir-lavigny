<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'start_datetime',
        'end_datetime',
        'content_id',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function customContent(): HasOne
    {
        return $this->hasOne(EventContent::class);
    }

    /**
     * Returns the content to display for this event:
     * - custom content if it exists
     * - otherwise linked global content
     * - otherwise null
     */
    public function getEffectiveContent(): ?string
    {
        if ($this->customContent) {
            return $this->customContent->body;
        } elseif ($this->content) {
            return $this->content->body;
        } else {
            return null;
        }
    }
}
