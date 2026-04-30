<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Plugin;

use Dskripchenko\LaravelAdmin\Admin;

/**
 * Контракт admin-плагина.
 *
 * Жизненный цикл: register() → boot().
 *   - register() вызывается до полной загрузки бинда — здесь можно бинды
 *     добавлять, миграции грузить.
 *   - boot() вызывается после, на этой стадии регистрируем Resources/Screens/
 *     Permissions через переданный $admin.
 *
 * Plugins декларируются в config/admin.php → plugins[] либо через
 * Admin::plugins([...]).
 */
interface AdminPlugin
{
    /**
     * Уникальный идентификатор плагина (для discovery, deps, audit).
     */
    public function name(): string;

    /**
     * Версия (для совместимости).
     */
    public function version(): string;

    /**
     * Слой register: бинды/миграции. Не имеет доступа к Admin manager'у —
     * слишком ранняя точка.
     */
    public function register(): void;

    /**
     * Слой boot: регистрация Resources/Screens/Permissions/Settings/Widgets
     * через переданный Admin.
     */
    public function boot(Admin $admin): void;
}
