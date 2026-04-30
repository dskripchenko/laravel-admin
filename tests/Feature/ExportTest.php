<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Export\CsvExporter;
use Dskripchenko\LaravelAdmin\Export\Exporter;
use Dskripchenko\LaravelAdmin\Export\ExporterRegistry;
use Dskripchenko\LaravelAdmin\Export\XlsxExporter;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        $t->string('password');
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
});

it('ExporterRegistry has built-in csv exporter', function (): void {
    $registry = app(ExporterRegistry::class);
    expect($registry->has('csv'))->toBeTrue();
    expect($registry->get('csv'))->toBeInstanceOf(CsvExporter::class);
});

it('ExporterRegistry registers xlsx when openspout installed', function (): void {
    expect(class_exists(OpenSpout\Writer\XLSX\Writer::class))->toBeTrue();
    expect(app(ExporterRegistry::class)->has('xlsx'))->toBeTrue();
});

it('ExporterRegistry::get throws on unknown format', function (): void {
    expect(fn () => app(ExporterRegistry::class)->get('unknown'))
        ->toThrow(InvalidArgumentException::class);
});

it('ExporterRegistry::add stores custom exporter by format', function (): void {
    $registry = new ExporterRegistry;
    $registry->add(new CsvExporter);

    $custom = new class implements Exporter
    {
        public function format(): string
        {
            return 'json';
        }

        public function mimeType(): string
        {
            return 'application/json';
        }

        public function extension(): string
        {
            return 'json';
        }

        public function export(iterable $rows, array $columns, string $filenameWithoutExt): Symfony\Component\HttpFoundation\StreamedResponse
        {
            return new Symfony\Component\HttpFoundation\StreamedResponse;
        }
    };
    $registry->add($custom);

    expect($registry->has('json'))->toBeTrue();
});

it('CsvExporter writes BOM + header + rows', function (): void {
    $exporter = new CsvExporter;
    $rows = [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ];
    $columns = ['name' => 'Name', 'email' => 'Email'];

    $response = $exporter->export($rows, $columns, 'export-2026');
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('export-2026.csv');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toStartWith("\xEF\xBB\xBF");
    expect($body)->toContain('Name');
    expect($body)->toContain('Alice');
    expect($body)->toContain('bob@example.com');
});

it('XlsxExporter generates XLSX with correct mime', function (): void {
    $exporter = new XlsxExporter;
    expect($exporter->mimeType())->toContain('spreadsheetml');
    expect($exporter->extension())->toBe('xlsx');

    $response = $exporter->export(
        [['name' => 'X', 'email' => 'x@example.com']],
        ['name' => 'Name', 'email' => 'Email'],
        'test',
    );

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    // XLSX начинается с PK (zip header).
    expect(substr($body, 0, 2))->toBe('PK');
});

it('export action with format=csv uses CsvExporter', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'email' => 'a@example.com', 'password' => 'p']);

    $response = $this->get('/api/admin/test-users/export?format=csv');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('Alice');
});

it('export action with format=xlsx returns XLSX', function (): void {
    TestResourceUserModel::create(['name' => 'B', 'email' => 'b@example.com', 'password' => 'p']);

    $response = $this->get('/api/admin/test-users/export?format=xlsx');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('spreadsheetml');
    expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
});

it('export action returns 422 for unknown format', function (): void {
    $response = $this->get('/api/admin/test-users/export?format=unknown');
    $response->assertStatus(422);
    expect($response->json('payload.errorKey'))->toBe('unsupported_format');
});

it('exportCsv action still works (backward compat)', function (): void {
    TestResourceUserModel::create(['name' => 'C', 'email' => 'c@example.com', 'password' => 'p']);

    $response = $this->get('/api/admin/test-users/exportCsv');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});
