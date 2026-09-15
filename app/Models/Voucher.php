<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
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

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /**
     * @return HasMany<TicketItem, $this>
     */
    public function ticketItems(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    public function isUsed(): bool
    {
        return $this->ticketItems()->exists();
    }

    protected static function booted(): void
    {
        static::updating(function (Voucher $voucher) {
            if ($voucher->ticketItems()->exists()) {
                abort(422, 'Impossible de modifier ce bon car il a été utilisé.');
            }
        });

        static::deleting(function (Voucher $voucher) {
            if ($voucher->ticketItems()->exists()) {
                abort(422, 'Impossible de supprimer ce bon car il a été utilisé.');
            }
        });
    }
}
