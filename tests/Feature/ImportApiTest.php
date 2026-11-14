<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Import\ImportProcess;
use Dskripchenko\LaravelAdmin\Import\ImportUploads;
use Dskripchenko\LaravelAdmin\Import\ImportWizardLayout;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

if (! class_exists('ImportableTestUserResource')) {
    /**
     * A resource that opts in to the import. Its fillModel() lower-cases the
     * e-mail, which shows the import writing through the resource.
     *
     * @internal
     */
    final class ImportableTestUserResource extends Resource
    {
        public static string $model = TestResourceUserModel::class;

        public static function slug(): string
        {
            return 'importable-users';
        }

        public function importable(): bool
        {
            return true;
        }

        public function fields(): array
        {
            return [
                Input::make('name')->required()->title('Имя'),
                Input::make('email')->type('email')->required()->title('Email')
                    ->rules(['unique:users,email']),
                Input::make('password')->onCreate()->onUpdate(false)->required()->title('Пароль'),
            ];
        }

        public function fillModel(Model $model, array $data): void
        {
            $data['email'] = strtolower((string) $data['email']);
            $model->forceFill($data);
        }
    }
}

function importAdmin(array $permissions): AdminUser
{
    $user = AdminUser::create([
        'name' => 'U',
        'email' => 'u-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'R', 'slug' => 'r-'.uniqid(), 'permissions' => $permissions]);
    $user->assignRole($role);

    return $user->refresh();
}

/** Signs another user in, in a fresh session. */
function switchImportUser(AdminUser $user): void
{
    test()->flushSession();
    test()->actingAs($user, 'admin');
}

/** Uploads a CSV through import/upload as the signed-in user and returns its path. */
function uploadImportCsv(string $content, string $resource = 'importable-users'): string
{
    $response = test()->postJson('/api/admin/import/upload', [
        'file' => UploadedFile::fake()->createWithContent('users.csv', $content),
        'resource' => $resource,
    ]);
    $response->assertOk();

    return (string) $response->json('payload.path');
}

beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestUserResource::class);
    $rr->add(ImportableTestUserResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        // A column the create form does not show.
        $t->boolean('is_admin')->default(false);
        $t->timestamps();
    });

    $this->admin = importAdmin(['admin.importable-users.view', 'admin.importable-users.create', 'admin.test-users.create']);
    $this->actingAs($this->admin, 'admin');

    Storage::fake('local');
});

it('import.upload stores the file in the user\'s own folder', function (): void {
    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAlice,a@example.com\n");

    $response = $this->postJson('/api/admin/import/upload', [
        'file' => $file,
        'resource' => 'importable-users',
    ]);

    $response->assertOk();
    expect($response->json('payload.disk'))->toBe('local');
    expect($response->json('payload.path'))->toStartWith(ImportUploads::directoryFor($this->admin).'/');
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

it('refuses every import step for a resource that does not opt in', function (): void {
    // TestUserResource keeps importable() = false; the user may create its records.
    $this->postJson('/api/admin/import/upload', [
        'file' => UploadedFile::fake()->createWithContent('users.csv', "name\nA\n"),
        'resource' => 'test-users',
    ])->assertStatus(403)->assertJsonPath('payload.errorKey', 'import_disabled');

    $path = uploadImportCsv("name,email,password\nA,a@example.com,p\n");
    $this->postJson('/api/admin/import/start', [
        'resource' => 'test-users',
        'path' => $path,
        'mapping' => ['name' => 'name', 'email' => 'email', 'password' => 'password'],
    ])->assertStatus(403)->assertJsonPath('payload.errorKey', 'import_disabled');

    expect(TestResourceUserModel::count())->toBe(0);
});

it('refuses the import to a user without the resource\'s create permission', function (): void {
    $path = uploadImportCsv("name,email,password\nA,a@example.com,p\n");

    // Signed in to the panel, may view the resource — not create in it.
    switchImportUser(importAdmin(['admin.importable-users.view']));

    $this->postJson('/api/admin/import/upload', [
        'file' => UploadedFile::fake()->createWithContent('users.csv', "name\nA\n"),
        'resource' => 'importable-users',
    ])->assertStatus(403)->assertJsonPath('payload.permission', 'admin.importable-users.create');
    $this->postJson('/api/admin/import/preview', [
        'resource' => 'importable-users',
        'path' => $path,
    ])->assertStatus(403);
    $this->postJson('/api/admin/import/start', [
        'resource' => 'importable-users',
        'path' => $path,
        'mapping' => ['name' => 'name', 'email' => 'email', 'password' => 'password'],
    ])->assertStatus(403);

    expect(TestResourceUserModel::count())->toBe(0);
});

it('import.preview returns headers + sample + auto-mapping', function (): void {
    $path = uploadImportCsv("name,email\nAlice,a@example.com\n");

    $response = $this->postJson('/api/admin/import/preview', [
        'resource' => 'importable-users',
        'path' => $path,
    ]);

    $response->assertOk();
    expect($response->json('payload.headers'))->toBe(['name', 'email']);
    expect($response->json('payload.sample.0.name'))->toBe('Alice');
    expect($response->json('payload.auto_mapping'))->toBe([
        'name' => 'name',
        'email' => 'email',
    ]);
});

it('import.preview and import.start read no file but the user\'s own upload', function (): void {
    Storage::disk('local')->put('private/contracts.csv', "name,email\nSecret,s@example.com\n");
    $own = uploadImportCsv("name,email\nAlice,a@example.com\n");
    $name = basename($own);

    // Another user's upload, a file elsewhere on the disk, ways out of the folder.
    switchImportUser(importAdmin(['admin.importable-users.create']));
    $paths = [
        $own,
        'private/contracts.csv',
        ImportUploads::directoryFor(auth('admin')->user()).'/../../private/contracts.csv',
        ImportUploads::directoryFor(auth('admin')->user()).'/..\\..\\private\\contracts.csv',
        '/private/contracts.csv',
        'imports/'.$name,
    ];
    foreach ($paths as $path) {
        $this->postJson('/api/admin/import/preview', ['resource' => 'importable-users', 'path' => $path])
            ->assertStatus(422)->assertJsonPath('payload.errorKey', 'file_missing');
        $this->postJson('/api/admin/import/start', [
            'resource' => 'importable-users',
            'path' => $path,
            'mapping' => ['name' => 'name', 'email' => 'email'],
        ])->assertStatus(422)->assertJsonPath('payload.errorKey', 'file_missing');
    }

    expect(ImportProcess::count())->toBe(0);
});

it('import.preview and import.start take no disk from the request', function (): void {
    Storage::fake('private');
    $path = uploadImportCsv("name,email\nAlice,a@example.com\n");
    Storage::disk('private')->put($path, "name,email\nSecret,s@example.com\n");

    $this->postJson('/api/admin/import/preview', [
        'resource' => 'importable-users',
        'path' => $path,
        'disk' => 'private',
    ])->assertStatus(422)->assertJsonPath('payload.errorKey', 'forbidden_disk');

    // Naming the configured disk is still accepted.
    $this->postJson('/api/admin/import/preview', [
        'resource' => 'importable-users',
        'path' => $path,
        'disk' => 'local',
    ])->assertOk()->assertJsonPath('payload.sample.0.name', 'Alice');
});

it('import.start creates the records through the resource\'s create path', function (): void {
    $path = uploadImportCsv("name,email,password\nAlice,ALICE@example.com,secret\nBob,b@example.com,secret\n");

    $response = $this->postJson('/api/admin/import/start', [
        'resource' => 'importable-users',
        'path' => $path,
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
    // fillModel() ran.
    expect(TestResourceUserModel::query()->where('name', 'Alice')->value('email'))->toBe('alice@example.com');
});

it('import.start maps columns onto the create form\'s fields only', function (): void {
    $path = uploadImportCsv("name,email,password,is_admin\nEve,e@example.com,p,1\n");

    $response = $this->postJson('/api/admin/import/start', [
        'resource' => 'importable-users',
        'path' => $path,
        'mapping' => [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
            'is_admin' => 'is_admin',
        ],
    ]);

    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('invalid_mapping');
    expect($response->json('payload.fields'))->toBe(['is_admin']);
    expect(TestResourceUserModel::count())->toBe(0);
});

it('the runner writes no column outside the create form, whatever the stored mapping', function (): void {
    $path = uploadImportCsv("name,email,password,is_admin\nEve,e@example.com,p,1\n");
    $process = ImportProcess::create([
        'resource_slug' => 'importable-users',
        'source_path' => $path,
        'mapping' => ['name' => 'name', 'email' => 'email', 'password' => 'password', 'is_admin' => 'is_admin'],
    ]);

    $result = app(Dskripchenko\LaravelAdmin\Import\ImportRunner::class)->run($process->id);

    expect((int) $result->created_count)->toBe(1);
    expect((bool) TestResourceUserModel::query()->where('name', 'Eve')->value('is_admin'))->toBeFalse();
});

it('import.start records error_count for invalid rows', function (): void {
    // 3 rows: 1 valid, 2 without the required email.
    $path = uploadImportCsv("name,email,password\nA,a@example.com,p\nB,,p\nC,,p\n");

    $response = $this->postJson('/api/admin/import/start', [
        'resource' => 'importable-users',
        'path' => $path,
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
        'resource' => 'importable-users',
        'path' => ImportUploads::directoryFor($this->admin).'/missing.csv',
        'mapping' => ['name' => 'name'],
    ]);
    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('file_missing');
});

it('import.status returns 404 for unknown id', function (): void {
    $response = $this->getJson('/api/admin/import/status?id=99999');
    $response->assertStatus(404);
});

it('import.status returns process info to its owner', function (): void {
    $process = ImportProcess::create([
        'resource_slug' => 'importable-users',
        'source_path' => 'imports/x.csv',
        'mapping' => ['n' => 'name'],
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->getKey(),
    ]);

    $response = $this->getJson('/api/admin/import/status?id='.$process->id);
    $response->assertOk();
    expect($response->json('payload.process.id'))->toBe($process->id);
    expect($response->json('payload.process.status'))->toBe('pending');
});

it('import.status hides another user\'s process unless the reader may import into its resource', function (): void {
    $process = ImportProcess::create([
        'resource_slug' => 'importable-users',
        'source_path' => 'imports/x.csv',
        'mapping' => ['n' => 'name'],
        'errors' => [['row' => 2, 'error' => 'secret value']],
        'owner_type' => $this->admin->getMorphClass(),
        'owner_id' => $this->admin->getKey(),
    ]);

    switchImportUser(importAdmin(['admin.importable-users.view']));
    $this->getJson('/api/admin/import/status?id='.$process->id)->assertStatus(404);

    switchImportUser(importAdmin(['admin.importable-users.create']));
    $this->getJson('/api/admin/import/status?id='.$process->id)->assertOk();
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
    expect($arr['children'][2]['props']['title'])->toBe((string) __('Предпросмотр'));
    expect($arr['children'][3]['props']['title'])->toBe((string) __('Импорт'));
    expect($arr['props']['persistKey'])->toBe('import-test-users');
});

it('the import reports its progress to the delayed process per row batch', function (): void {
    // 250 rows: reports after rows 100, 200 and the last one.
    $csv = "name,email,password\n";
    for ($i = 1; $i <= 250; $i++) {
        $csv .= "User {$i},u{$i}@example.com,p\n";
    }
    Storage::disk('local')->put('imports/users.csv', $csv);

    $reported = new class implements Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface
    {
        /** @var list<int> */
        public array $values = [];

        public function setProgress(int $percent): void
        {
            $this->values[] = $percent;
        }
    };
    app()->instance(Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface::class, $reported);

    $process = ImportProcess::create([
        'resource_slug' => 'test-users',
        'source_path' => 'imports/users.csv',
        'mapping' => ['name' => 'name', 'email' => 'email', 'password' => 'password'],
    ]);
    $result = app(Dskripchenko\LaravelAdmin\Import\ImportRunner::class)->run($process->id);

    expect($result->status)->toBe(ImportProcess::STATUS_COMPLETED);
    expect((int) $result->created_count)->toBe(250);
    expect($reported->values)->toBe([40, 80, 100]);
});
