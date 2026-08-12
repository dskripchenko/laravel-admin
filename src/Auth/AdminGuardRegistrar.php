<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Registers the admin guard through the runtime configuration, without
 * touching the host project's config/auth.php.
 *
 * The strategies, see config/admin.php → auth.strategy:
 *   - dedicated — adds the 'admin' guard, the 'admin_users' provider and the
 *                 'admin_users' password broker, over the default AdminUser
 *                 model.
 *   - shared    — registers nothing; the package reuses the host project's
 *                 existing guard, named in admin.auth.guard.
 *
 * It is called from AdminServiceProvider::boot(), after mergeConfigFrom.
 */
final class AdminGuardRegistrar
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function register(): void
    {
        $strategy = (string) $this->config->get('admin.auth.strategy', 'dedicated');

        if ($strategy !== 'dedicated') {
            return;
        }

        $this->apply(
            guard: (string) $this->config->get('admin.auth.guard', 'admin'),
            provider: (string) $this->config->get('admin.auth.provider', 'admin_users'),
            passwordBroker: (string) $this->config->get('admin.auth.password_broker', 'admin_users'),
            model: (string) $this->config->get('admin.auth.model', \Dskripchenko\LaravelAdmin\Models\AdminUser::class),
            table: (string) $this->config->get('admin.auth.table', 'admin_users'),
            resetTable: 'admin_password_resets',
        );
    }

    /**
     * An additional panel's guard, provider and broker, from its auth block:
     * admin.panels.{id}.auth.{strategy,guard,provider,model,…}.
     */
    public function registerFor(\Dskripchenko\LaravelAdmin\Panel\Panel $panel): void
    {
        $auth = $panel->auth;
        if ((string) ($auth['strategy'] ?? 'dedicated') !== 'dedicated') {
            return;
        }

        $this->apply(
            guard: $panel->guard,
            provider: (string) ($auth['provider'] ?? $panel->id.'_users'),
            passwordBroker: (string) ($auth['password_broker'] ?? $panel->id.'_users'),
            model: (string) ($auth['model'] ?? ''),
            table: (string) ($auth['table'] ?? $panel->id.'_users'),
            resetTable: (string) ($auth['password_reset_table'] ?? 'admin_password_resets'),
        );
    }

    private function apply(
        string $guard,
        string $provider,
        string $passwordBroker,
        string $model,
        string $table,
        string $resetTable,
    ): void {

        // Guard
        if ($this->config->get("auth.guards.{$guard}") === null) {
            $this->config->set("auth.guards.{$guard}", [
                'driver' => 'session',
                'provider' => $provider,
            ]);
        }

        // Provider
        if ($this->config->get("auth.providers.{$provider}") === null) {
            $this->config->set("auth.providers.{$provider}", [
                'driver' => 'eloquent',
                'model' => $model,
                'table' => $table,
            ]);
        }

        // Password broker
        if ($this->config->get("auth.passwords.{$passwordBroker}") === null) {
            $this->config->set("auth.passwords.{$passwordBroker}", [
                'provider' => $provider,
                'table' => $resetTable,
                'expire' => 60,
                'throttle' => 60,
            ]);
        }
    }
}
