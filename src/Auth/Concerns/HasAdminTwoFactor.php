<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\Concerns;

/**
 * The admin's two-factor authentication on a model of the host's own — the
 * `User` of the shared strategy.
 *
 * It casts the columns the admin's profile writes (`two_factor_secret`,
 * `two_factor_recovery_codes`, `two_factor_confirmed_at`, added to the users
 * table by the migration `admin:install --shared` publishes) exactly as
 * AdminUser does, and adds `hasTwoFactorEnabled()`, which the login asks
 * before it lets anyone in without a code. Without the trait the login has no
 * way to know that 2FA is on and skips the challenge.
 *
 * Not for a model that already keeps Laravel Fortify's 2FA in the same
 * columns: Fortify encrypts them by hand and the casts here would encrypt
 * them a second time.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasAdminTwoFactor
{
    public function initializeHasAdminTwoFactor(): void
    {
        $this->mergeCasts([
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ]);
    }

    /**
     * 2FA is enabled and confirmed.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->getAttribute('two_factor_secret') !== null
            && $this->getAttribute('two_factor_confirmed_at') !== null;
    }
}
