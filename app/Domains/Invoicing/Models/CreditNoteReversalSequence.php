<?php

namespace App\Domains\Invoicing\Models;

use Database\Factories\Domains\Invoicing\Models\CreditNoteReversalSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNoteReversalSequence extends Model
{
    /** @use HasFactory<CreditNoteReversalSequenceFactory> */
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
