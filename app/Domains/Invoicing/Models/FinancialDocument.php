<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property FinancialDocumentStatus $status
 * @property Carbon $generated_at
 * @property Carbon|null $archived_at
 */
class FinancialDocument extends Model
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'status',
        'disk',
        'path',
        'sha256',
        'error',
        'generated_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FinancialDocumentStatus::class,
            'generated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isStored(): bool
    {
        return $this->status === FinancialDocumentStatus::Stored;
    }
}
