<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\GlobalSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestSearchableUserResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('password')->nullable();
        $t->string('status')->nullable();
        $t->timestamps();
    });

    TestResourceUserModel::create(['name' => 'Ромашка Клиент', 'email' => 'romashka@example.com']);
    TestResourceUserModel::create(['name' => 'Одуванчик', 'email' => 'odu@example.com']);
    TestResourceUserModel::create(['name' => 'Berry', 'email' => 'roman@corp.io']);
});

function actingAdmin(array $permissions = ['*']): AdminUser
{
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => $permissions]);
    $admin->assignRole($role);
    test()->actingAs($admin->refresh(), 'admin');

    return $admin;
}

it('GlobalSearch service matches across searchable fields, case-insensitive', function (): void {
    actingAdmin();

    $groups = app(GlobalSearch::class)->search('roma');

    expect($groups)->toHaveCount(1);
    expect($groups[0]['slug'])->toBe('search-users');
    expect($groups[0]['label'])->toBe('Пользователи');

    $titles = collect($groups[0]['items'])->pluck('title')->all();
    // «Ромашка Клиент» (по name через транслит? нет — по email romashka@)
    // и «Berry» (email roman@corp.io) — оба матчат "roma" в email.
    expect($titles)->toContain('Ромашка Клиент');
    expect($titles)->toContain('Berry');
    expect($titles)->not->toContain('Одуванчик');
});

it('GlobalSearch item carries id + url to the record card', function (): void {
    actingAdmin();

    // 'дуванчик' — lowercase substring, совпадает и на sqlite (посимвольно).
    $groups = app(GlobalSearch::class)->search('дуванчик');
    $item = $groups[0]['items'][0];

    expect($item['title'])->toBe('Одуванчик');
    expect($item['subtitle'])->toBe('odu@example.com');
    expect($item['url'])->toBe('/r/search-users/'.$item['id']);
});

it('GlobalSearch skips resources the user cannot view', function (): void {
    actingAdmin(['admin.other.*']); // нет admin.search.users.view

    $groups = app(GlobalSearch::class)->search('ромашка');

    expect($groups)->toBe([]);
});

it('GlobalSearch respects per-resource limit and hasMore flag', function (): void {
    actingAdmin();
    foreach (range(1, 8) as $i) {
        TestResourceUserModel::create(['name' => "Row {$i}", 'email' => "dubl{$i}@x.io"]);
    }

    $groups = app(GlobalSearch::class)->search('dubl', null, 'admin', perResource: 5);

    expect($groups[0]['items'])->toHaveCount(5);
    expect($groups[0]['hasMore'])->toBeTrue();
});

it('system/search endpoint returns grouped results (min 2 chars)', function (): void {
    actingAdmin();

    $this->getJson('/api/admin/system/search?q=a')
        ->assertOk()
        ->assertJsonPath('payload.groups', []); // <2 символов → пусто

    $response = $this->getJson('/api/admin/system/search?q=romashka')->assertOk();
    expect($response->json('payload.query'))->toBe('romashka');
    expect($response->json('payload.groups.0.slug'))->toBe('search-users');
    expect($response->json('payload.groups.0.items.0.title'))->toBe('Ромашка Клиент');
});
