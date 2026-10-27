<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TableColumn::format() reaches the rows the list endpoint serves.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestFormattedResource::class);
    AdminApi::clearCache();

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password');
        $t->timestamps();
    });

    $admin = AdminUser::create([
        'name' => 'Fmt Admin',
        'email' => 'fmt-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'Super', 'slug' => 'fmt-super-'.uniqid(),
        'permissions' => ['*'],
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('search applies the column formatters to every row', function (): void {
    TestResourceUserModel::create(['name' => 'alice', 'email' => 'alice@e.com', 'password' => 'x']);

    $row = $this->postJson('/api/admin/test-formatteds/search')
        ->assertOk()
        ->json('payload.data.0');

    expect($row['name'])->toBe('ALICE')
        // A formatter sees the raw row, not its neighbours' output.
        ->and($row['email'])->toBe('alice <alice@e.com>');
});

it('formatRows leaves rows alone without formatters and reaches dotted names', function (): void {
    $rows = [['id' => 1, 'author' => ['name' => 'bob']]];

    expect(TableColumn::formatRows([TableColumn::make('id')], $rows))->toBe($rows);

    $out = TableColumn::formatRows([
        TableColumn::make('author.name')->format(static fn (mixed $v): string => ucfirst((string) $v)),
        TableColumn::make('missing')->format(static fn (): string => 'x'),
    ], $rows);

    expect($out)->toBe([['id' => 1, 'author' => ['name' => 'Bob']]]);
});

it('export applies the column formatters too: the file shows what the table shows', function (): void {
    TestResourceUserModel::create(['name' => 'alice', 'email' => 'alice@e.com', 'password' => 'x']);

    $csv = $this->get('/api/admin/test-formatteds/export?format=csv')->assertOk()->streamedContent();

    expect($csv)->toContain('ALICE')->toContain('alice <alice@e.com>');
});

it('writes XLSX without openspout: a valid workbook with escaped text and numbers', function (): void {
    $path = Dskripchenko\LaravelAdmin\Export\NativeXlsxExporter::write(
        [['n' => 42, 'name' => 'Tom & "Jerry" <cat>'], ['n' => 1.5, 'name' => "bell\x07 ring"]],
        ['n' => 'Number', 'name' => 'Name'],
    );
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();
    @unlink($path);

    expect($names)->toContain('[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml')
        ->and(simplexml_load_string($sheet))->not->toBeFalse()
        ->and($sheet)->toContain('<c><v>42</v></c>')
        ->and($sheet)->toContain('Tom &amp; &quot;Jerry&quot; &lt;cat&gt;')
        ->and($sheet)->toContain('bell ring')
        ->and($sheet)->toContain('<c t="inlineStr" s="1"><is><t xml:space="preserve">Number</t>');
});
