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
