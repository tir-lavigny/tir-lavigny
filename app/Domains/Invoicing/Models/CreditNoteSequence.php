<?php

namespace App\Domains\Invoicing\Models;

use Database\Factories\Domains\Invoicing\Models\CreditNoteSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNoteSequence extends Model
{
    /** @use HasFactory<CreditNoteSequenceFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
