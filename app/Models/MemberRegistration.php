<?php

namespace App\Models;

use App\Casts\EncryptedMemberFormData;
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

    protected $hidden = [
        'form_data',
    ];

    protected $appends = [
        'form_name',
    ];

    protected $casts = [
        'status' => MemberRegistrationStatus::class,
        'form_data' => EncryptedMemberFormData::class,
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

    public function getFormNameAttribute(): ?string
    {
        $formData = $this->form_data;
        $name = trim(($formData['first_name'] ?? '').' '.($formData['last_name'] ?? ''));

        return $name === '' ? null : $name;
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

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected function prepareActivityLog(array $changes): array
    {
        unset($changes['form_data']);

        return $changes;
    }
}
