<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Import\ColumnMapper;
use Dskripchenko\LaravelAdmin\Import\ImportPreviewService;
use Dskripchenko\LaravelAdmin\Import\ImportProcess;
use Illuminate\Support\Facades\Storage;

it('ColumnMapper exact match wins', function (): void {
    $mapping = ColumnMapper::autoMap(['name', 'email'], [
        Input::make('name'),
        Input::make('email'),
    ]);
    expect($mapping)->toBe(['name' => 'name', 'email' => 'email']);
});

it('ColumnMapper case-insensitive matches', function (): void {
    $mapping = ColumnMapper::autoMap(['Name', 'EMAIL'], [
        Input::make('name'),
        Input::make('email'),
    ]);
    expect($mapping)->toBe(['Name' => 'name', 'EMAIL' => 'email']);
});

it('ColumnMapper matches by Field::title()', function (): void {
    $mapping = ColumnMapper::autoMap(['Имя', 'E-почта'], [
        Input::make('name')->title('Имя'),
        Input::make('email')->title('E-почта'),
    ]);
    expect($mapping)->toBe(['Имя' => 'name', 'E-почта' => 'email']);
});

it('ColumnMapper matches snake_case from spaces/dashes', function (): void {
    $mapping = ColumnMapper::autoMap(['Created At', 'updated-at'], [
        Input::make('created_at'),
        Input::make('updated_at'),
    ]);
    expect($mapping)->toBe(['Created At' => 'created_at', 'updated-at' => 'updated_at']);
});

it('ColumnMapper drops unmatched headers', function (): void {
    $mapping = ColumnMapper::autoMap(['name', 'unknown'], [Input::make('name')]);
    expect($mapping)->toBe(['name' => 'name']);
    expect($mapping)->not->toHaveKey('unknown');
});

it('ColumnMapper::applyMapping translates row', function (): void {
    $mapped = ColumnMapper::applyMapping(
        ['Name' => 'Alice', 'EMAIL' => 'a@example.com', 'extra' => 'skip'],
        ['Name' => 'name', 'EMAIL' => 'email'],
    );
    expect($mapped)->toBe(['name' => 'Alice', 'email' => 'a@example.com']);
});

it('ImportPreviewService::preview reads CSV with BOM and headers', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "\xEF\xBB\xBFname,email\nAlice,a@example.com\nBob,b@example.com\n");

    $service = app(ImportPreviewService::class);
    $preview = $service->preview('local', 'imports/users.csv');

    expect($preview['format'])->toBe('csv');
    expect($preview['headers'])->toBe(['name', 'email']);
    expect($preview['sample'])->toHaveCount(2);
    expect($preview['sample'][0])->toBe(['name' => 'Alice', 'email' => 'a@example.com']);
    expect($preview['total'])->toBe(2);
});

it('ImportPreviewService limits sample to constructor-provided size', function (): void {
    Storage::fake('local');
    $rows = "name,n\n";
    for ($i = 0; $i < 50; $i++) {
        $rows .= "User $i,$i\n";
    }
    Storage::disk('local')->put('imports/big.csv', $rows);

    $service = new ImportPreviewService(5);
    $preview = $service->preview('local', 'imports/big.csv');

    expect($preview['sample'])->toHaveCount(5);
    expect($preview['total'])->toBe(50);
});

it('ImportPreviewService throws on missing file', function (): void {
    Storage::fake('local');
    expect(fn () => app(ImportPreviewService::class)->preview('local', 'imports/missing.csv'))
        ->toThrow(RuntimeException::class);
});

it('ImportPreviewService throws on unsupported extension', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('imports/data.json', '{}');
    expect(fn () => app(ImportPreviewService::class)->preview('local', 'imports/data.json'))
        ->toThrow(RuntimeException::class);
});

it('ImportPreviewService reads XLSX via openspout', function (): void {
    expect(class_exists(OpenSpout\Reader\XLSX\Reader::class))->toBeTrue();

    // Сгенерируем временный XLSX
    Storage::fake('local');
    $writer = new OpenSpout\Writer\XLSX\Writer;
    $tmpPath = sys_get_temp_dir().'/test-import-'.uniqid().'.xlsx';
    $writer->openToFile($tmpPath);
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['name', 'email']));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['Alice', 'a@example.com']));
    $writer->close();

    Storage::disk('local')->put('imports/file.xlsx', file_get_contents($tmpPath));
    @unlink($tmpPath);

    $preview = app(ImportPreviewService::class)->preview('local', 'imports/file.xlsx');
    expect($preview['format'])->toBe('xlsx');
    expect($preview['headers'])->toBe(['name', 'email']);
    expect($preview['sample'][0])->toBe(['name' => 'Alice', 'email' => 'a@example.com']);
});

it('ImportProcess Eloquent saves status + counters', function (): void {
    $p = ImportProcess::create([
        'resource_slug' => 'users',
        'source_path' => 'imports/users.csv',
        'mapping' => ['Name' => 'name'],
    ]);
    expect($p->status)->toBe(ImportProcess::STATUS_PENDING);
    expect($p->isFinished())->toBeFalse();

    $p->update(['status' => ImportProcess::STATUS_COMPLETED]);
    expect($p->fresh()->isFinished())->toBeTrue();
});
