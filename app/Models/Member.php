<?php

namespace App\Models;

use App\LogsActivity;
use App\Traits\SearchesAccentInsensitively;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, LogsActivity, SearchesAccentInsensitively, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'dav_uid',
    ];

    /**
     * @param  Builder<Member>  $query
     */
    public function scopeOrderByName(Builder $query): void
    {
        $this->orderByAccentInsensitive($query, ['first_name', 'last_name']);
    }

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
