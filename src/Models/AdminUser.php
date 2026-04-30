<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Models;

use Dskripchenko\LaravelAdmin\Audit\Concerns\Loggable;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

/**
 * Default-модель администратора панели.
 *
 * Подключается, когда `config('admin.auth.strategy')` = 'dedicated'. В режиме
 * 'shared' пользователь использует свою модель и подключает к ней trait
 * `HasAdminAccess` (появится в P2 вместе с RBAC).
 *
 * На фазе P0 — базовый набор полей. 2FA-колонки (two_factor_secret,
 * two_factor_recovery_codes, two_factor_confirmed_at) добавит P2 отдельной
 * миграцией. API-tokens trait (HasApiTokens из Sanctum) — опционально в P15.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $locale
 * @property string|null $theme
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property array<int,string>|null $two_factor_recovery_codes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AdminUser extends Model implements AuthenticatableContract, CanResetPasswordContract, MustVerifyEmailContract
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use HasAdminAccess;
    use HasFactory;
    use Loggable;
    use MustVerifyEmail;
    use Notifiable;

    /**
     * @var string
     */
    protected $table = 'admin_users';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'theme',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * 2FA включена и подтверждена.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }
}
