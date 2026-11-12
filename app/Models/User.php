<?php

namespace App\Models;

use App\Enums\AppAccess;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessApp(AppAccess $app): bool
    {
        return $this->can($app->value);
    }

    /**
     * Statc method to get members matching a role name.
     *
     * @return Collection<int, User>
     */
    private static function getMembersByRole(string $roleName): Collection
    {
        return self::role($roleName)->get();
    }

    /** @return array<string> */
    public static function getMemberRegistrationEmails(): array
    {
        $candidates = self::getMembersByRole('get member registration mails');
        if ($candidates->isEmpty()) {
            $candidates = self::where('email', config('app.user.email'))->get();
        }

        return $candidates->pluck('email')->toArray();
    }

    /** @return array<string> */
    public static function getRegisterCloseOperatingDayRemarkEmails(): array
    {
        $candidates = self::getMembersByRole('get register close operating day remark');
        if ($candidates->isEmpty()) {
            $candidates = self::where('email', config('app.user.email'))->get();
        }

        return $candidates->pluck('email')->toArray();
    }
}
