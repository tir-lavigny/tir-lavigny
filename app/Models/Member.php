<?php

namespace App\Models;

use App\Casts\EncryptedMemberData;
use App\LogsActivity;
use App\MemberStatus;
use App\Support\MemberDataCipher;
use App\Support\PiiMasker;
use App\Traits\SearchesAccentInsensitively;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property MemberStatus $status
 * @property string|null $email
 * @property string|null $avs
 * @property array<string, mixed>|null $dav_synced_snapshot
 * @property-read string|null $email_masked
 * @property-read string|null $avs_masked
 */
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, LogsActivity, SearchesAccentInsensitively, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'tel',
        'avs',
        'avs_hash',
        'is_swiss_citizen',
        'street',
        'npa',
        'city',
        'canton',
        'country',
        'status',
        'dav_uid',
        'dav_synced_at',
        'dav_synced_snapshot',
        'dav_sync_error',
        'anonymized_at',
    ];

    protected $hidden = [
        'avs',
        'avs_hash',
        'email',
        'dav_synced_snapshot',
    ];

    protected $appends = [
        'email_masked',
        'avs_masked',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'avs' => EncryptedMemberData::class,
            'is_swiss_citizen' => 'boolean',
            'dav_synced_at' => 'datetime',
            'dav_synced_snapshot' => 'array',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Member>  $query
     */
    public function scopeOrderByName(Builder $query): void
    {
        $this->orderByAccentInsensitive($query, ['first_name', 'last_name']);
    }

    /**
     * @param  Builder<Member>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', MemberStatus::ACTIVE);
    }

    /**
     * @param  Builder<Member>  $query
     */
    public function scopePassive(Builder $query): void
    {
        $query->where('status', MemberStatus::PASSIVE);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasOne<MemberRegistration, $this>
     */
    public function registration(): HasOne
    {
        return $this->hasOne(MemberRegistration::class);
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function getEmailMaskedAttribute(): ?string
    {
        return PiiMasker::email($this->email);
    }

    public function getAvsMaskedAttribute(): ?string
    {
        return PiiMasker::avs($this->avs);
    }

    protected static function booted(): void
    {
        static::saving(function (Member $member) {
            if ($member->isDirty('avs')) {
                $avs = $member->avs;
                $member->avs_hash = $avs === null || $avs === ''
                    ? null
                    : app(MemberDataCipher::class)->hash(preg_replace('/\D/', '', $avs) ?? '');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected function prepareActivityLog(array $changes): array
    {
        unset($changes['avs'], $changes['avs_hash']);

        if (array_key_exists('email', $changes)) {
            $changes['email'] = PiiMasker::email($changes['email'] === null ? null : (string) $changes['email']);
        }

        return $changes;
    }
}
