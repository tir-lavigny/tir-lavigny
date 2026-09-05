<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'dav_uid',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function registration(): HasOne
    {
        return $this->hasOne(MemberRegistration::class);
    }

    protected static function booted(): void
    {
        static::deleted(function (Member $member) {
            $member->vouchers()->whereDoesntHave('ticketItems')->delete();
        });

        static::restored(function (Member $member) {
            $member->vouchers()->whereDoesntHave('ticketItems')->restore();
        });
    }
}
