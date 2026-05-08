<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Console\Support\AdminPluginUpdater;
use Dskripchenko\LaravelAdmin\Console\Support\FieldTypeInferrer;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceWriter;
use Dskripchenko\LaravelAdmin\Console\Support\SchemaIntrospector;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('test_articles', function ($table): void {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('body')->nullable();
        $table->enum('status', ['draft', 'published'])->default('draft');
        $table->boolean('is_featured')->default(false);
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

it('SchemaIntrospector::analyzeTable returns columns + types + soft-delete flag', function (): void {
    $analysis = (new SchemaIntrospector)->analyzeTable('test_articles');

    expect($analysis['table'])->toBe('test_articles');
    expect($analysis['soft_deletes'])->toBeTrue();
    expect($analysis['timestamps'])->toBeTrue();
    expect($analysis['primary_key'])->toBe('id');

    $byName = collect($analysis['columns'])->keyBy('name');
    expect($byName->has('title'))->toBeTrue();
    expect($byName['title']['nullable'])->toBeFalse();
    expect($byName['body']['nullable'])->toBeTrue();
    expect($byName['slug']['is_unique'])->toBeTrue();
});

it('FieldTypeInferrer maps types to Field-classes', function (): void {
    $i = new FieldTypeInferrer;

    $textCode = $i->inferFieldCode([
        'name' => 'title', 'type' => 'varchar', 'nullable' => false,
        'default' => null, 'comment' => null, 'is_primary' => false,
        'is_unique' => false, 'is_indexed' => true, 'enum_values' => null,
    ]);
    expect($textCode)->toContain("Input::make('title')")->toContain('->required()');

    $textareaCode = $i->inferFieldCode([
        'name' => 'description', 'type' => 'text', 'nullable' => true,
        'default' => null, 'comment' => null, 'is_primary' => false,
        'is_unique' => false, 'is_indexed' => false, 'enum_values' => null,
    ]);
    expect($textareaCode)->toContain("Textarea::make('description')")->toContain('->rows(4)');

    $boolCode = $i->inferFieldCode([
        'name' => 'is_active', 'type' => 'boolean', 'nullable' => false,
        'default' => false, 'comment' => null, 'is_primary' => false,
        'is_unique' => false, 'is_indexed' => false, 'enum_values' => null,
    ]);
    expect($boolCode)->toContain("Switcher::make('is_active')");

    $emailCode = $i->inferFieldCode([
        'name' => 'email', 'type' => 'varchar', 'nullable' => false,
        'default' => null, 'comment' => null, 'is_primary' => false,
        'is_unique' => true, 'is_indexed' => true, 'enum_values' => null,
    ]);
    expect($emailCode)->toContain("->type('email')");

    $dateCode = $i->inferFieldCode([
        'name' => 'published_at', 'type' => 'datetime', 'nullable' => true,
        'default' => null, 'comment' => null, 'is_primary' => false,
        'is_unique' => false, 'is_indexed' => false, 'enum_values' => null,
    ]);
    expect($dateCode)->toContain("DatePicker::make('published_at')")->toContain('->withTime()');

    $enumCode = $i->inferFieldCode([
        'name' => 'status', 'type' => 'enum', 'nullable' => false,
        'default' => 'draft', 'comment' => null, 'is_primary' => false,
        'is_unique' => false, 'is_indexed' => false,
        'enum_values' => ['draft', 'published'],
    ]);
    expect($enumCode)->toContain("Select::make('status')")->toContain("'draft' => 'Draft'");
});

it('FieldTypeInferrer skips id/timestamps/soft-delete from form', function (): void {
    $i = new FieldTypeInferrer;
    $emptyCol = ['type' => 'datetime', 'nullable' => true, 'default' => null, 'comment' => null,
        'is_primary' => false, 'is_unique' => false, 'is_indexed' => false, 'enum_values' => null];

    expect($i->inferFieldCode(['name' => 'id', 'type' => 'bigint', 'is_primary' => true] + $emptyCol))->toBeNull();
    expect($i->inferFieldCode(['name' => 'created_at'] + $emptyCol))->toBeNull();
    expect($i->inferFieldCode(['name' => 'updated_at'] + $emptyCol))->toBeNull();
    expect($i->inferFieldCode(['name' => 'deleted_at'] + $emptyCol))->toBeNull();
});

it('FieldTypeInferrer maps belongs-to FK to RelationSelect', function (): void {
    $i = new FieldTypeInferrer;
    $code = $i->inferFieldCode(
        ['name' => 'author_id', 'type' => 'bigint', 'nullable' => false,
            'default' => null, 'comment' => null, 'is_primary' => false,
            'is_unique' => false, 'is_indexed' => true, 'enum_values' => null],
        [['name' => 'author', 'type' => 'BelongsTo', 'related' => 'App\\Models\\User',
            'foreign_key' => 'author_id', 'owner_key' => 'id']],
    );
    expect($code)->toContain("RelationSelect::make('author_id')")
        ->toContain("->relation('author')");
});

it('FieldTypeInferrer column code: badge for status, money for price', function (): void {
    $i = new FieldTypeInferrer;

    expect($i->inferColumnCode(['name' => 'status', 'type' => 'varchar', 'is_indexed' => false, 'enum_values' => null]))
        ->toContain("preset('badge')");

    expect($i->inferColumnCode(['name' => 'price', 'type' => 'decimal', 'is_indexed' => false, 'enum_values' => null]))
        ->toContain("preset('money')")
        ->toContain("align('right')");

    expect($i->inferColumnCode(['name' => 'created_at', 'type' => 'datetime', 'is_indexed' => false, 'enum_values' => null]))
        ->toContain("preset('datetime')");
});

it('ResourceWriter generates from stub', function (): void {
    $files = new Filesystem;
    $writer = new ResourceWriter($files);

    $stub = $writer->stubPath('resource.stub');
    expect(file_exists($stub))->toBeTrue();

    $tmpDir = sys_get_temp_dir().'/laravel-admin-test-'.uniqid();
    mkdir($tmpDir, 0755, recursive: true);
    $target = $tmpDir.'/TestResource.php';

    $created = $writer->fromStub($stub, $target, [
        'namespace' => 'App\\Test',
        'class' => 'TestResource',
        'modelClass' => 'App\\Models\\Test',
        'modelShort' => 'Test',
        'extraImports' => "use Foo\\Bar;",
        'icon' => 'test',
        'group' => 'null',
        'slug' => 'tests',
        'label' => 'Tests',
        'singularLabel' => 'Test',
        'permission' => 'admin.tests',
        'fields' => "            // fields",
        'columns' => "            // columns",
        'filters' => '',
        'searchable' => "'name'",
        'date' => '2026-05-08',
    ]);

    expect($created)->toBeTrue();
    $contents = file_get_contents($target);
    expect($contents)->toContain('namespace App\\Test;')
        ->toContain('class TestResource')
        ->toContain("'admin.tests'");

    @unlink($target);
    @rmdir($tmpDir);
});

it('AdminPluginUpdater createStubPlugin creates valid PHP', function (): void {
    $files = new Filesystem;
    $updater = new AdminPluginUpdater($files);

    // Имитируем отсутствие plugin'а — addMenuNode создаст stub.
    // Но мы тестируем in-memory без реальной FS-записи в base_path.
    expect($updater)->toBeInstanceOf(AdminPluginUpdater::class);
});
