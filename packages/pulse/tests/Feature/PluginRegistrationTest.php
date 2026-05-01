<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminPulse\AdminPulsePlugin;
use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Dskripchenko\LaravelAdminPulse\Resources\PulseSampleResource;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Illuminate\Support\Carbon;

final class PluginRegistrationTest extends TestCase
{
    public function test_plugin_in_admin_plugins_config(): void
    {
        $this->assertContains(AdminPulsePlugin::class, (array) config('admin.plugins', []));
    }

    public function test_resource_registered(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $this->assertContains(PulseSampleResource::class, $admin->getResources());
    }

    public function test_permissions_registered(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);
        $this->assertTrue($admin->getPermissionRegistry()->knows('admin.system.pulse.view'));
    }

    public function test_aggregate_command_runs(): void
    {
        $this->artisan('admin:pulse:aggregate', ['--minutes' => 5])->assertSuccessful();
    }

    public function test_rotate_command_deletes_old_records(): void
    {
        PulseSample::query()->create([
            'kind' => 'request',
            'key' => 'old',
            'label' => null,
            'duration_ms' => 1,
            'status_code' => 200,
            'meta' => null,
            'sampled_at' => Carbon::now()->subDays(2),
        ]);
        PulseAggregate::query()->create([
            'bucket' => 'route.percentiles',
            'key' => 'old',
            'metrics' => [],
            'period_start' => Carbon::now()->subDays(30),
            'period_end' => Carbon::now()->subDays(30),
            'aggregated_at' => Carbon::now()->subDays(30),
        ]);

        $this->artisan('admin:pulse:rotate')->assertSuccessful();

        // sample (2 days old) is older than 24 hours → deleted
        $this->assertSame(0, PulseSample::query()->count());
        // aggregate (30 days old) is older than 7 days → deleted
        $this->assertSame(0, PulseAggregate::query()->count());
    }
}
