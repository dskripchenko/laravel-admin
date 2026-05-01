<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminQuill;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

final class AdminQuillPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'quill';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function register(): void {}

    public function boot(Admin $admin): void {}
}
