<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Support\TableColumns;
use Dskripchenko\LaravelAdmin\Tests\Fixtures\Shared\TestSharedUser;
use Dskripchenko\LaravelAdmin\Theme\LocaleResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A host users table without the panel's optional `locale` and `theme`
// columns: choosing a language or a theme is remembered by the cookie alone,
// instead of failing on the missing column.

beforeEach(function (): void {
    Schema::table('users', function (Blueprint $table): void {
        $table->dropColumn(['locale', 'theme']);
    });

    $user = TestSharedUser::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret-pass']);
    $user->assignRole(Role::query()->create(['name' => 'Super', 'slug' => 'super-'.uniqid(), 'permissions' => ['*']]));
    $this->actingAs($user->refresh(), 'web');
});

it('sets the locale on a users table without the locale column', function (): void {
    expect(Schema::hasColumn('users', 'locale'))->toBeFalse();

    $response = $this->postJson('/api/admin/system/setLocale', ['locale' => 'en']);

    $response->assertOk()->assertJsonPath('payload.locale', 'en');
    $response->assertCookie(LocaleResolver::COOKIE_NAME, 'en');
});

it('persists the locale into a cookie alone when the column is missing', function (): void {
    $cookie = app(LocaleResolver::class)->persist('en');

    expect($cookie->getValue())->toBe('en');
    expect(TableColumns::has(new TestSharedUser, 'locale'))->toBeFalse();
    expect(TableColumns::has(new TestSharedUser, 'email'))->toBeTrue();
});

it('sets the theme on a users table without the theme column', function (): void {
    $this->postJson('/api/admin/system/setTheme', ['theme' => 'dark'])->assertOk();
});
