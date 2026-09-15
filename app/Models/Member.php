<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'dav_uid',
    ];

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasOne<MemberRegistration, $this>
     */
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
