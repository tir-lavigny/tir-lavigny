<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\Product;
use Database\Factories\Domains\Invoicing\Models\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $unit_price
 * @property string $total
 * @property int $unit_price_cents
 * @property int $total_cents
 */
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'unit_price_cents',
        'total',
        'total_cents',
        'position',
        'metadata',
    ];

    protected $hidden = ['unit_price_cents', 'total_cents'];

    protected $appends = ['unit_price', 'total'];

    protected function casts(): array
    {
        return [
            'type' => InvoiceItemType::class,
            'quantity' => 'decimal:3',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function unitPrice(): Attribute
    {
        return $this->moneyAttribute('unit_price');
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function total(): Attribute
    {
        return $this->moneyAttribute('total');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
