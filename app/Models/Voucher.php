<?php

namespace App\Models;

use App\LogsActivity;
use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory, HasMoneyColumns, LogsActivity, SoftDeletes;

    protected $fillable = [
        'discount_percent',
        'discount_fixed',
        'discount_fixed_cents',
        'product_id',
        'season_id',
        'member_id',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
    ];

    protected function discountFixed(): Attribute
    {
        return $this->legacyMoneyAttribute('discount_fixed');
    }

    protected function discountFixedCents(): Attribute
    {
        return $this->centsMoneyAttribute('discount_fixed');
    }

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
            if ($voucher->isUsed()) {
                abort(422, 'Impossible de modifier ce bon car il a été utilisé.');
            }
        });

        static::deleting(function (Voucher $voucher) {
            if ($voucher->isUsed()) {
                abort(422, 'Impossible de supprimer ce bon car il a été utilisé.');
            }
        });
    }
}
