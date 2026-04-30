<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

/**
 * Test-AdminUser с подключённым Sanctum HasApiTokens.
 * В production host-проект подключает HasApiTokens напрямую к своей модели.
 */
final class TestApiTokensAdminUser extends AdminUser
{
    use HasApiTokens;
}

beforeEach(function (): void {
    if (! Schema::hasTable('personal_access_tokens')) {
        Schema::create('personal_access_tokens', function (Blueprint $t): void {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    config()->set('admin.auth.model', TestApiTokensAdminUser::class);
    config()->set('admin.auth.api_tokens.enabled', true);

    $this->admin = TestApiTokensAdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($this->admin, 'admin');
});

it('profile.tokensList returns empty initially', function (): void {
    $response = $this->getJson('/api/admin/profile/tokensList');
    $response->assertOk();
    expect($response->json('payload.data'))->toBe([]);
});

it('profile.tokenCreate returns plain_text_token + token meta', function (): void {
    $response = $this->postJson('/api/admin/profile/tokenCreate', [
        'name' => 'CI deploy',
        'abilities' => ['admin.users.view'],
        'expires_in_days' => 30,
    ]);
    $response->assertOk();

    expect($response->json('payload.plain_text_token'))->toBeString();
    expect($response->json('payload.plain_text_token'))->not->toBeEmpty();
    expect($response->json('payload.token.name'))->toBe('CI deploy');
    expect($response->json('payload.token.abilities'))->toBe(['admin.users.view']);
    expect($response->json('payload.token.expires_at'))->not->toBeNull();
});

it('profile.tokenCreate with no abilities defaults to [*]', function (): void {
    $response = $this->postJson('/api/admin/profile/tokenCreate', ['name' => 'X']);
    $response->assertOk();
    expect($response->json('payload.token.abilities'))->toBe(['*']);
});

it('profile.tokenCreate validates input', function (): void {
    $this->postJson('/api/admin/profile/tokenCreate', [])
        ->assertStatus(422); // missing name
    $this->postJson('/api/admin/profile/tokenCreate', [
        'name' => str_repeat('x', 300), // too long
    ])->assertStatus(422);
    $this->postJson('/api/admin/profile/tokenCreate', [
        'name' => 'X',
        'expires_in_days' => 0,
    ])->assertStatus(422);
});

it('profile.tokensList returns created tokens', function (): void {
    $this->postJson('/api/admin/profile/tokenCreate', ['name' => 'A'])->assertOk();
    $this->postJson('/api/admin/profile/tokenCreate', ['name' => 'B'])->assertOk();

    $response = $this->getJson('/api/admin/profile/tokensList');
    $tokens = $response->json('payload.data');
    expect($tokens)->toHaveCount(2);
    expect(collect($tokens)->pluck('name')->all())->toContain('A', 'B');
});

it('profile.tokenRevoke deletes token', function (): void {
    $created = $this->postJson('/api/admin/profile/tokenCreate', ['name' => 'X'])
        ->json('payload.token.id');

    $this->postJson('/api/admin/profile/tokenRevoke', ['id' => $created])
        ->assertOk();

    $response = $this->getJson('/api/admin/profile/tokensList');
    expect($response->json('payload.data'))->toBe([]);
});

it('profile.tokenRevoke 404 for non-existent token', function (): void {
    $response = $this->postJson('/api/admin/profile/tokenRevoke', ['id' => 99999]);
    $response->assertStatus(404);
});

it('profile.tokenRevoke does not see other users tokens', function (): void {
    $other = TestApiTokensAdminUser::create([
        'name' => 'O',
        'email' => 'o-'.uniqid().'@example.com',
        'password' => 'p',
    ]);
    $foreignToken = $other->createToken('foreign');

    $response = $this->postJson('/api/admin/profile/tokenRevoke', [
        'id' => $foreignToken->accessToken->getKey(),
    ]);
    $response->assertStatus(404);
});

it('profile.tokensList returns 404 if Sanctum not used by AdminUser', function (): void {
    config()->set('admin.auth.model', AdminUser::class);
    $admin = AdminUser::create([
        'name' => 'X', 'email' => 'x-'.uniqid().'@example.com', 'password' => 'p',
    ]);
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/api/admin/profile/tokensList');
    $response->assertStatus(404);
    expect($response->json('payload.errorKey'))->toBe('sanctum_unavailable');
});
