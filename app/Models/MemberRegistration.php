<?php

namespace App\Models;

use App\LogsActivity;
use App\MemberRegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MemberRegistration extends Model
{
    use LogsActivity;

    protected $fillable = [
        'status',
        'dav_uid',
        'member_id',
        'form_data',
        'form_submitted_at',
        'error_message',
    ];

    protected $casts = [
        'status' => MemberRegistrationStatus::class,
        'form_data' => 'array',
        'form_submitted_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function formUrl(): string
    {
        return config('app.url').'/member-registration/'.$this->form_uuid;
    }

    protected static function booted(): void
    {
        static::creating(function ($memberRegistration) {
            $memberRegistration->form_uuid = Str::uuid();
            $memberRegistration->status = MemberRegistrationStatus::PENDING;
        });
    }
}
