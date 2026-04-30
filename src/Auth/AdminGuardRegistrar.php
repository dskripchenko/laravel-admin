<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Регистратор admin-guard'а через runtime-конфигурацию, без правки
 * config/auth.php host-проекта.
 *
 * Стратегии (см. config/admin.php → auth.strategy):
 *   - dedicated  — добавляем guard 'admin' + provider 'admin_users' + password
 *                  broker 'admin_users'. Используется default AdminUser-модель.
 *   - shared     — ничего не регистрируем; пакет переиспользует уже имеющийся
 *                  guard host-проекта (имя задано в admin.auth.guard).
 *
 * Вызывается из AdminServiceProvider::boot() после mergeConfigFrom.
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

        $guard = (string) $this->config->get('admin.auth.guard', 'admin');
        $provider = (string) $this->config->get('admin.auth.provider', 'admin_users');
        $passwordBroker = (string) $this->config->get('admin.auth.password_broker', 'admin_users');
        $model = (string) $this->config->get('admin.auth.model', \Dskripchenko\LaravelAdmin\Models\AdminUser::class);
        $table = (string) $this->config->get('admin.auth.table', 'admin_users');

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
                'table' => 'admin_password_resets',
                'expire' => 60,
                'throttle' => 60,
            ]);
        }
    }
}
