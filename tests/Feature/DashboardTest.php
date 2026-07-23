<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Widget\DashboardLayout;

beforeEach(function (): void {
    $this->admin = AdminUser::create([
        'name' => 'D',
        'email' => 'd-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'V', 'slug' => 'v-'.uniqid(), 'permissions' => ['*']]);
    $this->admin->assignRole($role);
    $this->actingAs($this->admin->refresh(), 'admin');
});

it('DashboardScreen::layout returns Dashboard with declared widgets by default', function (): void {
    $screen = new TestDashboard;
    $compiled = $screen->compile();

    expect($compiled['layout'])->toHaveCount(1);
    expect($compiled['layout'][0]['type'])->toBe('dashboard');
    expect($compiled['layout'][0]['children'])->toHaveCount(3);
    expect($compiled['layout'][0]['props']['key'])->toBe('test-dashboard');
});

it('DashboardScreen applies persisted layout (reorder + size)', function (): void {
    DashboardLayout::create([
        'dashboard_key' => 'test-dashboard',
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->id,
        'widgets' => [
            ['slug' => 'markdown', 'size' => 12, 'position' => 0],
            ['slug' => 'test-stats-a', 'size' => 4, 'position' => 1],
            // 'test-stats-b' опущен — попадёт в конец как «новый».
        ],
    ]);

    $screen = new TestDashboard;
    $compiled = $screen->compile();
    $children = $compiled['layout'][0]['children'];
    expect($children)->toHaveCount(3);
    // Markdown widget — первым с size=12.
    expect($children[0]['slug'])->toBe('markdown');
    expect($children[0]['size'])->toBe(12);
    expect($children[1]['slug'])->toBe('test-stats-a');
    expect($children[1]['size'])->toBe(4);
    // 'test-stats-b' — в конце, без override size.
    expect($children[2]['slug'])->toBe('test-stats-b');
});

it('DashboardScreen filters out hidden widgets', function (): void {
    DashboardLayout::create([
        'dashboard_key' => 'test-dashboard',
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->id,
        'widgets' => [
            ['slug' => 'test-stats-a', 'position' => 0, 'hidden' => true],
            ['slug' => 'test-stats-b', 'position' => 1],
            ['slug' => 'markdown', 'position' => 2],
        ],
    ]);

    $screen = new TestDashboard;
    $compiled = $screen->compile();
    $slugs = array_column($compiled['layout'][0]['children'], 'slug');
    expect($slugs)->toBe(['test-stats-b', 'markdown']);
});

it('dashboard.get returns null when no persisted layout', function (): void {
    $response = $this->getJson('/api/admin/dashboard/get?key=test');
    $response->assertOk();
    expect($response->json('payload.layout'))->toBeNull();
});

it('dashboard.save creates DashboardLayout row', function (): void {
    $response = $this->postJson('/api/admin/dashboard/save', [
        'key' => 'test',
        'widgets' => [
            ['slug' => 'a', 'size' => 6, 'position' => 0],
            ['slug' => 'b', 'size' => 6, 'position' => 1],
        ],
    ]);

    $response->assertOk();
    expect($response->json('payload.widgets'))->toHaveCount(2);

    $row = DashboardLayout::first();
    expect($row->dashboard_key)->toBe('test');
    expect((int) $row->owner_id)->toBe((int) $this->admin->id);
});

it('dashboard.save validates widget structure', function (): void {
    $response = $this->postJson('/api/admin/dashboard/save', [
        'key' => 'test',
        'widgets' => [
            ['slug' => 'a', 'size' => 99],   // size > 12 → 422
        ],
    ]);
    $response->assertStatus(422);
});

it('dashboard.save updates existing row (idempotent)', function (): void {
    $this->postJson('/api/admin/dashboard/save', [
        'key' => 'test',
        'widgets' => [['slug' => 'a', 'size' => 4]],
    ])->assertOk();

    $this->postJson('/api/admin/dashboard/save', [
        'key' => 'test',
        'widgets' => [['slug' => 'a', 'size' => 8]],
    ])->assertOk();

    expect(DashboardLayout::count())->toBe(1);
    expect(DashboardLayout::first()->widgets[0]['size'])->toBe(8);
});

it('dashboard.reset deletes the row', function (): void {
    $this->postJson('/api/admin/dashboard/save', [
        'key' => 'test',
        'widgets' => [['slug' => 'a']],
    ])->assertOk();
    expect(DashboardLayout::count())->toBe(1);

    $this->postJson('/api/admin/dashboard/reset', ['key' => 'test'])->assertOk();
    expect(DashboardLayout::count())->toBe(0);
});

it('dashboard.get returns saved layout for current user only', function (): void {
    DashboardLayout::create([
        'dashboard_key' => 'test-dashboard',
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->id,
        'widgets' => [['slug' => 'a', 'size' => 5]],
    ]);
    $other = AdminUser::create([
        'name' => 'X', 'email' => 'x-'.uniqid().'@example.com', 'password' => 'p',
    ]);
    DashboardLayout::create([
        'dashboard_key' => 'test-dashboard',
        'owner_type' => $other->getMorphClass(),
        'owner_id' => $other->id,
        'widgets' => [['slug' => 'b', 'size' => 9]],
    ]);

    $response = $this->getJson('/api/admin/dashboard/get?key=test-dashboard');
    expect($response->json('payload.layout'))->toBe([['slug' => 'a', 'size' => 5]]);
});

it('dashboard.savePeriod persists per-user period and get returns it (BL-16)', function (): void {
    // до сохранения — период null
    expect($this->getJson('/api/admin/dashboard/get?key=test')->json('payload.period'))->toBeNull();

    $this->postJson('/api/admin/dashboard/savePeriod', ['key' => 'test', 'period' => '90d'])
        ->assertOk()
        ->assertJsonPath('payload.period', '90d');

    // персистентно и возвращается в get
    expect($this->getJson('/api/admin/dashboard/get?key=test')->json('payload.period'))->toBe('90d');
});

it('dashboard.savePeriod does not clobber an existing layout (BL-16)', function (): void {
    $this->postJson('/api/admin/dashboard/save', [
        'key' => 'test',
        'widgets' => [['slug' => 'w1', 'size' => 6, 'position' => 0]],
    ])->assertOk();

    $this->postJson('/api/admin/dashboard/savePeriod', ['key' => 'test', 'period' => '7d'])->assertOk();

    $get = $this->getJson('/api/admin/dashboard/get?key=test');
    expect($get->json('payload.period'))->toBe('7d');
    expect($get->json('payload.layout'))->toHaveCount(1); // layout сохранён
});
