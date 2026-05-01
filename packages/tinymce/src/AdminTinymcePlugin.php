<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminTinymce;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

/**
 * AdminTinymcePlugin — placeholder для плагина TinyMCE-WYSIWYG.
 *
 * Не вводит свои Resource'ы / Permissions. Frontend-side через
 *
 * @dskripchenko/laravel-admin-tinymce npm package + registerField('wysiwyg',
 * TinymceField).
 */
final class AdminTinymcePlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'tinymce';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function register(): void {}

    public function boot(Admin $admin): void
    {
        // No-op — TinyMCE существует только как frontend-driver через npm.
    }
}
