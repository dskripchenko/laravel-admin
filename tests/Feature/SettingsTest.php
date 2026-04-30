<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;
use Dskripchenko\LaravelAdmin\Settings\Storage\KeyValueSettingsStorage;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    /** @var SettingsRegistry $sr */
    $sr = app(SettingsRegistry::class);
    $sr->clear();
    $sr->add(TestBrandSettings::class);
    AdminApi::clearCache();

    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'S', 'slug' => 's-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('SettingsResource::slug strips Settings/Resource suffix', function (): void {
    expect(TestBrandSettings::slug())->toBe('test-brand');
});

it('SettingsResource::permission default = admin.settings.{slug}', function (): void {
    expect(TestBrandSettings::permission())->toBe('admin.settings.test-brand');
});

it('SettingsResource::defaults uses Field::default values', function (): void {
    $defaults = (new TestBrandSettings)->defaults();
    expect($defaults)->toBe([
        'site_name' => 'My Site',
        'contact_email' => 'contact@example.com',
        'items_per_page' => 25,
    ]);
});

it('KeyValueSettingsStorage roundtrip — save/get/all', function (): void {
    $storage = app(KeyValueSettingsStorage::class);
    $storage->save('brand', ['site_name' => 'Acme', 'items' => 50]);

    expect($storage->get('brand', 'site_name'))->toBe('Acme');
    expect($storage->all('brand'))->toBe(['site_name' => 'Acme', 'items' => 50]);
});

it('KeyValueSettingsStorage::save merges, replace overwrites', function (): void {
    $storage = app(KeyValueSettingsStorage::class);
    $storage->save('brand', ['a' => 1, 'b' => 2]);
    $storage->save('brand', ['b' => 22]); // merge: a остаётся
    expect($storage->all('brand'))->toBe(['a' => 1, 'b' => 22]);

    $storage->replace('brand', ['c' => 3]);
    expect($storage->all('brand'))->toBe(['c' => 3]);
});

it('KeyValueSettingsStorage::forget removes single key', function (): void {
    $storage = app(KeyValueSettingsStorage::class);
    $storage->save('brand', ['a' => 1, 'b' => 2]);
    $storage->forget('brand', 'a');
    expect($storage->all('brand'))->toBe(['b' => 2]);
});

it('KeyValueSettingsStorage handles array values', function (): void {
    $storage = app(KeyValueSettingsStorage::class);
    $storage->save('brand', ['palette' => ['#ff0', '#0f0']]);
    expect($storage->get('brand', 'palette'))->toBe(['#ff0', '#0f0']);
});

it('SettingsResource::read merges defaults over storage', function (): void {
    $storage = app(KeyValueSettingsStorage::class);
    $storage->save('test-brand', ['site_name' => 'Custom']);

    $values = (new TestBrandSettings)->read($storage);
    expect($values['site_name'])->toBe('Custom');
    expect($values['contact_email'])->toBe('contact@example.com'); // default
});

it('SettingsResource::write validates against rules', function (): void {
    $storage = app(KeyValueSettingsStorage::class);

    expect(fn () => (new TestBrandSettings)->write($storage, [
        'site_name' => '',  // required violated
        'contact_email' => 'not-an-email',
        'items_per_page' => 5,
    ]))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('settings.{slug}.read returns merged values', function (): void {
    $response = $this->getJson('/api/admin/settings_test-brand/read');
    $response->assertOk();
    expect($response->json('payload.values.site_name'))->toBe('My Site');
    expect($response->json('payload.values.items_per_page'))->toBe(25);
});

it('settings.{slug}.update saves new values', function (): void {
    $response = $this->postJson('/api/admin/settings_test-brand/update', [
        'values' => [
            'site_name' => 'Updated',
            'contact_email' => 'new@example.com',
            'items_per_page' => 50,
        ],
    ]);
    $response->assertOk();
    expect($response->json('payload.values.site_name'))->toBe('Updated');

    $row = DB::table('admin_settings')
        ->where('group', 'test-brand')
        ->where('key', 'site_name')
        ->first();
    expect($row)->not->toBeNull();
});

it('settings.{slug}.update returns 422 on invalid payload', function (): void {
    $response = $this->postJson('/api/admin/settings_test-brand/update', [
        'values' => [
            'site_name' => '',
            'contact_email' => 'not-email',
            'items_per_page' => 999, // > 100
        ],
    ]);
    $response->assertStatus(422);
});

it('settings.{slug}.meta returns fields and permissions', function (): void {
    $response = $this->getJson('/api/admin/settings_test-brand/meta');
    $response->assertOk();
    expect($response->json('payload.kind'))->toBe('settings');
    expect($response->json('payload.slug'))->toBe('test-brand');
    expect($response->json('payload.fields'))->toHaveCount(3);
    expect($response->json('payload.permissions.update'))->toBe('admin.settings.test-brand.update');
});

it('settings update gated by admin.settings.{slug}.update permission', function (): void {
    $user = AdminUser::create([
        'name' => 'V', 'email' => 'v-'.uniqid().'@example.com', 'password' => 'p',
    ]);
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.settings.test-brand.view'],
    ]);
    $user->assignRole($role);
    $this->actingAs($user->refresh(), 'admin');

    // Чтение разрешено.
    $this->getJson('/api/admin/settings_test-brand/read')->assertOk();
    // Обновление — 403.
    $this->postJson('/api/admin/settings_test-brand/update', [
        'values' => ['site_name' => 'X'],
    ])->assertStatus(403);
});

it('Manifest::build includes settings block', function (): void {
    $manifest = app(Dskripchenko\LaravelAdmin\Support\Manifest::class)->build('ru');
    $brand = collect($manifest['settings'])->firstWhere('slug', 'test-brand');
    expect($brand)->not->toBeNull();
    expect($brand['fields'])->toHaveCount(3);
});
