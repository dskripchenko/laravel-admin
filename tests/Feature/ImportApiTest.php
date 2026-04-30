<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Import\ImportProcess;
use Dskripchenko\LaravelAdmin\Import\ImportWizardLayout;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestUserResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->timestamps();
    });

    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => ['*']]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');

    Storage::fake('local');
});

it('import.upload stores file and returns disk+path', function (): void {
    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAlice,a@example.com\n");

    $response = $this->postJson('/api/admin/import/upload', [
        'file' => $file,
        'resource' => 'test-users',
    ]);

    $response->assertOk();
    expect($response->json('payload.disk'))->toBe('local');
    expect($response->json('payload.path'))->toStartWith('imports/');
    Storage::disk('local')->assertExists($response->json('payload.path'));
});

it('import.upload returns 422 for unknown resource', function (): void {
    $response = $this->postJson('/api/admin/import/upload', [
        'file' => UploadedFile::fake()->create('x.csv'),
        'resource' => 'unknown-slug',
    ]);
    $response->assertStatus(422);
});

it('import.preview returns headers + sample + auto-mapping', function (): void {
    Storage::disk('local')->put('imports/users.csv', "name,email\nAlice,a@example.com\n");

    $response = $this->postJson('/api/admin/import/preview', [
        'resource' => 'test-users',
        'path' => 'imports/users.csv',
    ]);

    $response->assertOk();
    expect($response->json('payload.headers'))->toBe(['name', 'email']);
    expect($response->json('payload.sample.0.name'))->toBe('Alice');
    expect($response->json('payload.auto_mapping'))->toBe([
        'name' => 'name',
        'email' => 'email',
    ]);
});

it('import.start creates ImportProcess and runs synchronously', function (): void {
    Storage::disk('local')->put('imports/users.csv', "name,email,password\nAlice,a@example.com,secret\nBob,b@example.com,secret\n");

    $response = $this->postJson('/api/admin/import/start', [
        'resource' => 'test-users',
        'path' => 'imports/users.csv',
        'mapping' => [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
        ],
    ]);

    $response->assertOk();
    $process = $response->json('payload.process');
    expect($process['status'])->toBe('completed');
    expect((int) $process['created_count'])->toBe(2);
    expect(TestResourceUserModel::count())->toBe(2);
});

it('import.start records error_count for invalid rows', function (): void {
    // 3 rows: 1 валидная, 2 без required email.
    Storage::disk('local')->put('imports/users.csv', "name,email,password\nA,a@example.com,p\nB,,p\nC,,p\n");

    $response = $this->postJson('/api/admin/import/start', [
        'resource' => 'test-users',
        'path' => 'imports/users.csv',
        'mapping' => [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
        ],
    ]);

    $response->assertOk();
    $process = $response->json('payload.process');
    expect((int) $process['created_count'])->toBe(1);
    expect((int) $process['error_count'])->toBe(2);
    expect($process['errors'])->toHaveCount(2);
});

it('import.start returns 422 for missing file', function (): void {
    $response = $this->postJson('/api/admin/import/start', [
        'resource' => 'test-users',
        'path' => 'imports/missing.csv',
        'mapping' => ['name' => 'name'],
    ]);
    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('file_missing');
});

it('import.status returns 404 for unknown id', function (): void {
    $response = $this->getJson('/api/admin/import/status?id=99999');
    $response->assertStatus(404);
});

it('import.status returns process info', function (): void {
    $process = ImportProcess::create([
        'resource_slug' => 'test-users',
        'source_path' => 'imports/x.csv',
        'mapping' => ['n' => 'name'],
    ]);

    $response = $this->getJson('/api/admin/import/status?id='.$process->id);
    $response->assertOk();
    expect($response->json('payload.process.id'))->toBe($process->id);
    expect($response->json('payload.process.status'))->toBe('pending');
});

it('Resource::importable default false; meta.features.importable reflects', function (): void {
    expect((new TestUserResource)->importable())->toBeFalse();

    $importable = new class extends Dskripchenko\LaravelAdmin\Resource\Resource
    {
        public static string $model = TestResourceUserModel::class;

        public function importable(): bool
        {
            return true;
        }
    };
    expect($importable->importable())->toBeTrue();
    expect($importable->meta()['features']['importable'])->toBeTrue();
});

it('ImportWizardLayout::for produces 4-step Wizard', function (): void {
    $w = ImportWizardLayout::for('test-users');
    $arr = $w->toArray();

    expect($arr['type'])->toBe('wizard');
    expect($arr['children'])->toHaveCount(4);
    expect($arr['children'][0]['props']['title'])->toBe('Загрузка файла');
    expect($arr['children'][1]['props']['title'])->toBe('Сопоставление колонок');
    expect($arr['children'][2]['props']['title'])->toBe('Предпросмотр');
    expect($arr['children'][3]['props']['title'])->toBe('Импорт');
    expect($arr['props']['persistKey'])->toBe('import-test-users');
});
