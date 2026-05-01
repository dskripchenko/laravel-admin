<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminQuill\Tests\Feature;

use Dskripchenko\LaravelAdminQuill\AdminQuillPlugin;
use Dskripchenko\LaravelAdminQuill\Tests\TestCase;

final class PluginRegistrationTest extends TestCase
{
    public function test_plugin_in_admin_plugins_config(): void
    {
        $this->assertContains(AdminQuillPlugin::class, (array) config('admin.plugins', []));
    }

    public function test_config_published(): void
    {
        $this->assertSame('snow', config('admin-quill.default_options.theme'));
    }
}
