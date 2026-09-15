<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'is_restricted',
    ];

    protected $casts = [
        'is_restricted' => 'boolean',
    ];

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::deleted(function (Category $category) {
            $category->products()->delete();
        });

        static::restored(function (Category $category) {
            $category->products()->restore();
        });
    }
}
