<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminTinymce\Tests\Feature;

use Dskripchenko\LaravelAdminTinymce\AdminTinymcePlugin;
use Dskripchenko\LaravelAdminTinymce\Tests\TestCase;

final class PluginRegistrationTest extends TestCase
{
    public function test_plugin_in_admin_plugins_config(): void
    {
        $this->assertContains(AdminTinymcePlugin::class, (array) config('admin.plugins', []));
    }

    public function test_config_published(): void
    {
        $this->assertNotNull(config('admin-tinymce.default_init.height'));
        $this->assertSame(400, config('admin-tinymce.default_init.height'));
    }
}
