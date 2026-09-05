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
    ];

    protected $casts = [
        'status' => MemberRegistrationStatus::class,
        'form_data' => 'array',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    protected static function booted(): void
    {
        static::creating(function ($memberRegistration) {
            $memberRegistration->form_uuid = Str::uuid();
            $memberRegistration->status = MemberRegistrationStatus::PENDING;
        });
    }
}
