<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

beforeEach(function (): void {
    /** @var ScreenRegistry $registry */
    $registry = app(ScreenRegistry::class);
    $registry->clear();
    $registry->add(TestContactScreen::class);
    AdminApi::clearCache();

    TestContactScreen::$sent = [];

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

it('Screen::slug strips Screen suffix and kebab-cases', function (): void {
    expect(TestContactScreen::slug())->toBe('test-contact');
});

it('GET /api/admin/{slug}/state returns compiled snapshot', function (): void {
    $response = $this->getJson('/api/admin/test-contact/state');
    $response->assertOk();
    expect($response->json('payload.name'))->toBe('Contact');
    expect($response->json('payload.description'))->toBe('Тестовая форма');
    expect($response->json('payload.state.email'))->toBe('');
    expect($response->json('payload.layout'))->toHaveCount(1);
    expect($response->json('payload.layout.0.type'))->toBe('rows');
    expect($response->json('payload.command_bar'))->toHaveCount(1);
    expect($response->json('payload.command_bar.0.attributes.method'))->toBe('send');
    expect($response->json('payload.etag'))->toBeString();
});

it('etag is stable across identical state snapshots', function (): void {
    $a = $this->getJson('/api/admin/test-contact/state')->json('payload.etag');
    $b = $this->getJson('/api/admin/test-contact/state')->json('payload.etag');
    expect($a)->toBe($b);
});

it('POST /runMethod dispatches command method with payload', function (): void {
    $response = $this->postJson('/api/admin/test-contact/runMethod', [
        'method' => 'send',
        'payload' => [
            'email' => 'foo@example.com',
            'message' => 'Hello world',
        ],
    ]);

    $response->assertOk();
    expect($response->json('payload.message'))->toBe('Письмо отправлено');
    expect($response->json('payload.alerts.0.type'))->toBe('success');
    expect(TestContactScreen::$sent)->toHaveCount(1);
    expect(TestContactScreen::$sent[0]['email'])->toBe('foo@example.com');
});

it('POST /runMethod returns 422 on validation failure', function (): void {
    $response = $this->postJson('/api/admin/test-contact/runMethod', [
        'method' => 'send',
        'payload' => [
            'email' => 'not-an-email',
            'message' => 'x',
        ],
    ]);
    $response->assertStatus(422);
    expect(TestContactScreen::$sent)->toHaveCount(0);
});

it('POST /runMethod 404 when method is not callable', function (): void {
    $response = $this->postJson('/api/admin/test-contact/runMethod', [
        'method' => 'query',
    ]);
    $response->assertStatus(404);
});

it('POST /runMethod 400 when method is missing', function (): void {
    $response = $this->postJson('/api/admin/test-contact/runMethod', [
        'payload' => [],
    ]);
    $response->assertStatus(400);
});

it('Screen permission gates state action', function (): void {
    /** @var ScreenRegistry $registry */
    $registry = app(ScreenRegistry::class);
    $registry->clear();
    $registry->add(TestProtectedScreen::class);
    AdminApi::clearCache();

    // Юзер без permission'а видит 403.
    $user = AdminUser::create([
        'name' => 'V', 'email' => 'v-'.uniqid().'@example.com', 'password' => 'p',
    ]);
    $role = Role::create([
        'name' => 'V', 'slug' => 'v-'.uniqid(),
        'permissions' => ['admin.unrelated'],
    ]);
    $user->assignRole($role);
    $this->actingAs($user->refresh(), 'admin');

    $this->getJson('/api/admin/test-protected/state')->assertStatus(403);
});

it('Manifest::build exposes registered screens (excluding generated)', function (): void {
    $manifest = app(Dskripchenko\LaravelAdmin\Support\Manifest::class)->build('ru');
    $entry = collect($manifest['screens'])->firstWhere('slug', 'test-contact');
    expect($entry)->not->toBeNull();
    expect($entry['name'])->toBe('Contact');
});
