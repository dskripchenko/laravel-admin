<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class DemoAdminUserResource extends Resource
{
    public static string $model = AdminUser::class;

    public static function slug(): string
    {
        return 'demo-admin-users';
    }
}

function demoAdmin(): AdminUser
{
    $admin = AdminUser::create(['name' => 'Demo', 'email' => 'demo-'.uniqid().'@example.com', 'password' => 'demo-pass']);
    $admin->assignRole(Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => ['*']]));

    return $admin->refresh();
}

function enableDemo(array $overrides = []): void
{
    config()->set('admin.demo', array_merge(config('admin.demo'), ['enabled' => true, 'readonly' => true], $overrides));
}

it('leaves the bootstrap without demo data when demo mode is off', function (): void {
    $this->getJson('/api/admin/system/bootstrap')
        ->assertOk()
        ->assertJsonPath('payload.demo', null);
});

it('puts the demo accounts into the bootstrap', function (): void {
    enableDemo(['accounts' => [
        ['label' => 'Administrator', 'email' => 'admin@demo.test', 'password' => 'demo', 'description' => 'Full access'],
        ['email' => 'viewer@demo.test', 'password' => 'demo'],
        ['label' => 'Broken entry without a password', 'email' => 'x@demo.test'],
    ]]);

    $demo = $this->getJson('/api/admin/system/bootstrap')->assertOk()->json('payload.demo');

    expect($demo['readonly'])->toBeTrue()
        ->and($demo['accounts'])->toBe([
            ['label' => 'Administrator', 'email' => 'admin@demo.test', 'password' => 'demo', 'description' => 'Full access'],
            ['label' => 'viewer@demo.test', 'email' => 'viewer@demo.test', 'password' => 'demo', 'description' => null],
        ]);
});

it('signs a demo account in through the ordinary login', function (): void {
    enableDemo();
    $admin = demoAdmin();

    $this->postJson('/api/admin/auth/login', ['email' => $admin->email, 'password' => 'demo-pass'])
        ->assertOk()
        ->assertJsonPath('payload.user.id', $admin->id);
});

it('refuses the blocked actions with demo_readonly', function (string $url): void {
    enableDemo();
    $this->actingAs(demoAdmin(), 'admin');

    $this->postJson($url, ['current_password' => 'demo-pass', 'password' => 'x', 'password_confirmation' => 'x', 'name' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'demo_readonly');
})->with([
    'password' => '/api/admin/profile/changePassword',
    'profile' => '/api/admin/profile/update',
    '2fa' => '/api/admin/profile/twoFactorEnable',
    'token' => '/api/admin/profile/tokenCreate',
    'impersonation' => '/api/admin/auth/startImpersonation',
]);

it('translates the refusal', function (): void {
    enableDemo();
    app()->setLocale('en');
    $this->actingAs(demoAdmin(), 'admin');

    $this->withHeader('X-Admin-Locale', 'en')
        ->postJson('/api/admin/profile/changePassword', [])
        ->assertForbidden()
        ->assertJsonPath('payload.message', 'Demo mode: this action is disabled.');
});

it('lets everything through when demo mode is off or not read-only', function (array $demo): void {
    config()->set('admin.demo', array_merge(config('admin.demo'), $demo));
    $this->actingAs(demoAdmin(), 'admin');

    // The validation answers, not the demo guard.
    $this->postJson('/api/admin/profile/changePassword', [])->assertStatus(422);
})->with([
    'off' => [['enabled' => false]],
    'writable' => [['enabled' => true, 'readonly' => false]],
]);

it('protects the user model resources from writes but not from reads', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->add(DemoAdminUserResource::class);
    AdminApi::clearCache();

    enableDemo();
    $admin = demoAdmin();
    $this->actingAs($admin, 'admin');

    $this->postJson('/api/admin/demo-admin-users/update', ['id' => $admin->id, 'name' => 'Hacked'])
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'demo_readonly');
    $this->postJson('/api/admin/demo-admin-users/delete', ['id' => $admin->id])
        ->assertForbidden();
    $this->getJson('/api/admin/demo-admin-users/read?id='.$admin->id)->assertOk();

    expect($admin->fresh()->name)->toBe('Demo');

    // An explicit, empty list switches the protection off.
    config()->set('admin.demo.protected_models', []);
    $this->postJson('/api/admin/demo-admin-users/delete', ['id' => $admin->id])
        ->assertJsonMissing(['errorKey' => 'demo_readonly']);
});

it('refuses uploads over the demo limit', function (): void {
    Storage::fake('local');
    enableDemo(['max_upload_kb' => 1]);
    $this->actingAs(demoAdmin(), 'admin');

    $this->postJson('/api/admin/uploads/upload', ['file' => UploadedFile::fake()->create('big.pdf', 5, 'application/pdf')])
        ->assertForbidden()
        ->assertJsonPath('payload.errorKey', 'demo_readonly');

    $this->postJson('/api/admin/uploads/upload', ['file' => UploadedFile::fake()->createWithContent('small.txt', 'tiny')])
        ->assertOk();
});
