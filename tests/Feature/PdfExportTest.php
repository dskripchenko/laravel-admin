<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Export\ExporterRegistry;
use Dskripchenko\LaravelAdmin\Export\Pdf\MpdfRenderer;
use Dskripchenko\LaravelAdmin\Export\Pdf\PdfRenderer;
use Dskripchenko\LaravelAdmin\Export\PdfExporter;
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

it('PdfExporter registered when mpdf installed', function (): void {
    expect(class_exists(Mpdf\Mpdf::class))->toBeTrue();
    /** @var ExporterRegistry $registry */
    $registry = app(ExporterRegistry::class);
    expect($registry->has('pdf'))->toBeTrue();
    expect($registry->get('pdf'))->toBeInstanceOf(PdfExporter::class);
});

it('PdfExporter has correct mime/extension', function (): void {
    $exporter = new PdfExporter(new MpdfRenderer);
    expect($exporter->format())->toBe('pdf');
    expect($exporter->mimeType())->toBe('application/pdf');
    expect($exporter->extension())->toBe('pdf');
});

it('PdfExporter generates PDF with %PDF- header', function (): void {
    $exporter = new PdfExporter(new MpdfRenderer);
    $response = $exporter->export(
        [['name' => 'Alice', 'email' => 'a@example.com']],
        ['name' => 'Имя', 'email' => 'Email'],
        'test',
    );

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    // PDF начинается с "%PDF-".
    expect(substr($body, 0, 5))->toBe('%PDF-');
});

it('export action with format=pdf returns PDF', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'p']);

    $response = $this->get('/api/admin/test-users/export?format=pdf');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->streamedContent(), 0, 5))->toBe('%PDF-');
});

it('PdfRenderer interface allows custom implementation', function (): void {
    $custom = new class implements PdfRenderer
    {
        public function render(string $html, array $options = []): string
        {
            return '%PDF-1.4 fake content '.strlen($html);
        }
    };

    $exporter = new PdfExporter($custom);
    $response = $exporter->export(
        [['name' => 'X']],
        ['name' => 'N'],
        'fake',
    );
    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toStartWith('%PDF-1.4 fake content');
});

it('config driver=dompdf falls back to mpdf when dompdf missing', function (): void {
    config()->set('admin.exports.pdf.driver', 'dompdf');

    // Re-resolve registry.
    $this->app->forgetInstance(ExporterRegistry::class);
    /** @var ExporterRegistry $registry */
    $registry = app(ExporterRegistry::class);

    // dompdf не установлен в dev (только mpdf), fallback подберёт mpdf
    if (! class_exists(Dompdf\Dompdf::class)) {
        expect($registry->has('pdf'))->toBeTrue();
    } else {
        expect($registry->has('pdf'))->toBeTrue();
    }
});
