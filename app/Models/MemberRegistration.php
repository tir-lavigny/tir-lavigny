<?php

namespace App\Models;

use App\LogsActivity;
use App\MemberRegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberRegistration extends Model
{
    use LogsActivity;

    protected $fillable = [
        'form_uuid',
        'status',
        'dav_uid',
        'member_id',
        'form_data',
        'form_submitted_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'status' => MemberRegistrationStatus::class,
        'form_data' => 'array',
        'form_submitted_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function formUrl(): string
    {
        return rtrim(config('app.url'), '/').'/member-registration/'.$this->form_uuid;
    }

    protected static function booted(): void
    {
        static::creating(function ($memberRegistration) {
            if (! $memberRegistration->form_uuid) {
                $memberRegistration->form_uuid = Str::uuid();
            }
            if (! $memberRegistration->status) {
                $memberRegistration->status = MemberRegistrationStatus::PENDING;
            }
        });
    }
}
