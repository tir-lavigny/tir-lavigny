<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'start_datetime',
        'end_datetime',
        'content_id',
        'location_id',
        'event_chain_id',
        'overridden_fields',
        'has_chain_conflict',
        'pending_conflict_fields',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'overridden_fields' => 'array',
        'has_chain_conflict' => 'boolean',
        'pending_conflict_fields' => 'array',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<EventChain, $this>
     */
    public function eventChain(): BelongsTo
    {
        return $this->belongsTo(EventChain::class);
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * @return HasOne<EventContent, $this>
     */
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

    // ─── Chain helpers ────────────────────────────────────────────────────────

    /**
     * Returns true when the given field has been individually customised
     * on this event (i.e. chain propagation will be skipped for it).
     */
    public function isFieldOverridden(string $field): bool
    {
        return in_array($field, $this->overridden_fields ?? [], true);
    }

    /**
     * Marks a field as individually customised so future chain updates
     * will not overwrite it (they will flag a conflict instead).
     */
    public function markFieldOverridden(string $field): void
    {
        $overridden = $this->overridden_fields ?? [];
        if (! in_array($field, $overridden, true)) {
            $overridden[] = $field;
            $this->overridden_fields = array_values($overridden);
        }
    }

    /**
     * Unmarks a field override (e.g. after conflict is resolved via "accept chain").
     */
    public function unmarkFieldOverridden(string $field): void
    {
        $this->overridden_fields = array_values(
            array_filter($this->overridden_fields ?? [], fn ($f) => $f !== $field)
        );
    }

    /**
     * Detaches this event from its chain, making it a standalone event.
     * Clears chain-related metadata without touching event content.
     */
    public function detachFromChain(): void
    {
        $this->event_chain_id = null;
        $this->overridden_fields = null;
        $this->has_chain_conflict = false;
        $this->pending_conflict_fields = null;
        $this->save();
    }
}
