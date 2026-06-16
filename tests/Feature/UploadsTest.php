<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => ['*']]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('uploads.upload stores file and returns metadata', function (): void {
    $file = UploadedFile::fake()->createWithContent('doc.pdf', 'fake content')
        ->mimeType('application/pdf');

    $response = $this->postJson('/api/admin/uploads/upload', ['file' => $file]);
    $response->assertOk();

    $data = $response->json('payload');
    expect($data['disk'])->toBe('local');
    expect($data['path'])->toStartWith('uploads/');
    expect($data['name'])->toBe('doc.pdf');
    expect($data['mime'])->toBe('application/pdf');
    Storage::disk('local')->assertExists($data['path']);
});

it('uploads.upload validates required file', function (): void {
    $response = $this->postJson('/api/admin/uploads/upload', []);
    $response->assertStatus(422);
});

it('uploads.image accepts image and stores under images/', function (): void {
    $image = UploadedFile::fake()->image('avatar.png', 100, 100);

    $response = $this->postJson('/api/admin/uploads/image', ['file' => $image]);
    $response->assertOk();

    $data = $response->json('payload');
    expect($data['path'])->toContain('uploads/images/');
    expect($data['mime'])->toContain('image/');
});

it('uploads.image rejects non-image with 422', function (): void {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
    $response = $this->postJson('/api/admin/uploads/image', ['file' => $file]);
    $response->assertStatus(422);
});

it('uploads.upload returns admin-serve URL not Storage::url', function (): void {
    $image = UploadedFile::fake()->image('a.png', 50, 50);
    $response = $this->postJson('/api/admin/uploads/upload', ['file' => $image]);
    $url = $response->json('payload.url');
    expect($url)->toStartWith('/api/admin/uploads/serve?');
    expect($url)->toContain('disk=local');
});

it('uploads.serve streams file from whitelisted disk', function (): void {
    Storage::disk('local')->put('uploads/test.txt', 'hello');
    config(['admin.uploads.servable_disks' => ['local']]);

    $response = $this->get('/api/admin/uploads/serve?disk=local&path=uploads/test.txt');
    $response->assertOk();
    ob_start();
    $response->sendContent();
    $body = ob_get_clean();
    expect($body)->toBe('hello');
});

it('uploads.serve rejects disk outside whitelist with 422', function (): void {
    config(['admin.uploads.servable_disks' => ['public']]);
    Storage::disk('local')->put('uploads/secret.txt', 'nope');

    $response = $this->get('/api/admin/uploads/serve?disk=local&path=uploads/secret.txt');
    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('forbidden_disk');
});

it('uploads.serve 404 when file missing', function (): void {
    config(['admin.uploads.servable_disks' => ['local']]);
    $response = $this->get('/api/admin/uploads/serve?disk=local&path=uploads/missing.png');
    $response->assertStatus(404);
});

it('uploads.serve 422 when disk or path missing', function (): void {
    $response = $this->get('/api/admin/uploads/serve');
    $response->assertStatus(422);
});

it('uploads.upload respects max_kilobytes limit', function (): void {
    config()->set('admin.uploads.max_kilobytes', 1); // 1 KB
    $file = UploadedFile::fake()->create('big.pdf', 50, 'application/pdf'); // 50 KB

    $response = $this->postJson('/api/admin/uploads/upload', ['file' => $file]);
    $response->assertStatus(422);
});

it('uploads.image respects max_kilobytes_image limit', function (): void {
    config()->set('admin.uploads.max_kilobytes_image', 1);
    $file = UploadedFile::fake()->image('big.png')->size(50);

    $response = $this->postJson('/api/admin/uploads/image', ['file' => $file]);
    $response->assertStatus(422);
});

it('uploads.upload uses configured disk', function (): void {
    Storage::fake('public');
    config()->set('admin.uploads.disk', 'public');

    $response = $this->postJson('/api/admin/uploads/upload', [
        'file' => UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'),
    ]);
    $response->assertOk();
    expect($response->json('payload.disk'))->toBe('public');
    Storage::disk('public')->assertExists($response->json('payload.path'));
});

it('uploads endpoints require auth', function (): void {
    $this->app['auth']->guard('admin')->logout();
    $this->postJson('/api/admin/uploads/upload', [])->assertStatus(401);
    $this->postJson('/api/admin/uploads/image', [])->assertStatus(401);
});
