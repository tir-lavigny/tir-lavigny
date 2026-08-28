<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductProduct extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'child_product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];
}
