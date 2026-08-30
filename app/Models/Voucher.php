<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'discount_percent',
        'discount_fixed',
        'product_id',
        'season_id',
        'member_id',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'discount_fixed' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function ticketItems(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    public function isUsed(): bool
    {
        return $this->ticketItems()->exists();
    }
}
