<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\MorphSwitcher;
use Dskripchenko\LaravelAdmin\Field\RelationSelect;
use Dskripchenko\LaravelAdmin\Field\RelationTable;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }
});

it('RelationSelect has type=relation_select and stores relation config', function (): void {
    $f = RelationSelect::make('user_id')
        ->relation(TestResourceUserModel::class, 'name', 'id')
        ->searchable(['name', 'email'])
        ->preload(['profile']);

    expect($f->fieldType())->toBe('relation_select');
    expect($f->getAttribute('relatedModel'))->toBe(TestResourceUserModel::class);
    expect($f->getAttribute('displayColumn'))->toBe('name');
    expect($f->getAttribute('valueColumn'))->toBe('id');
    expect($f->getAttribute('searchColumns'))->toBe(['name', 'email']);
    expect($f->getAttribute('preload'))->toBe(['profile']);
});

it('RelationSelect::eager preloads choices from DB', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'email' => 'a@example.com', 'password' => 'x']);
    TestResourceUserModel::create(['name' => 'Bob', 'email' => 'b@example.com', 'password' => 'x']);

    $f = RelationSelect::make('user_id')
        ->relation(TestResourceUserModel::class, 'name', 'id')
        ->eager();

    $choices = $f->getAttribute('options');
    expect($choices)->toHaveCount(2);
    expect(array_column($choices, 'label'))->toContain('Alice', 'Bob');
});

it('RelationSelect::eager respects limit', function (): void {
    for ($i = 0; $i < 5; $i++) {
        TestResourceUserModel::create([
            'name' => "User $i",
            'email' => "u$i@example.com",
            'password' => 'x',
        ]);
    }

    $f = RelationSelect::make('user_id')
        ->relation(TestResourceUserModel::class, 'name', 'id')
        ->eager(3);

    expect($f->getAttribute('options'))->toHaveCount(3);
});

it('RelationSelect::eager без relation() — no-op', function (): void {
    $f = RelationSelect::make('foo')->eager();
    expect($f->getAttribute('options'))->toBeNull();
});

it('RelationTable has type=relation_table and config', function (): void {
    $f = RelationTable::make('comments')
        ->relation('comments')
        ->model(TestResourceUserModel::class)
        ->columns([
            TableColumn::make('id'),
            TableColumn::make('text'),
        ])
        ->with(['author'])
        ->withPivot(['role']);

    expect($f->fieldType())->toBe('relation_table');
    expect($f->getAttribute('relation'))->toBe('comments');
    expect($f->getAttribute('relatedModel'))->toBe(TestResourceUserModel::class);
    expect($f->getAttribute('columns'))->toHaveCount(2);
    expect($f->getAttribute('with'))->toBe(['author']);
    expect($f->getAttribute('withPivot'))->toBe(['role']);
});

it('MorphSwitcher::morph stores type config', function (): void {
    $f = MorphSwitcher::make('subject')
        ->morph('post', TestResourceUserModel::class, 'title', 'id')
        ->morph('user', TestResourceUserModel::class);

    expect($f->fieldType())->toBe('morph_switcher');
    $types = $f->getMorphTypes();
    expect($types)->toHaveKeys(['post', 'user']);
    expect($types['post']['model'])->toBe(TestResourceUserModel::class);
    expect($types['post']['displayColumn'])->toBe('title');
    expect($types['user']['displayColumn'])->toBe('name'); // default
});

it('MorphSwitcher::morphMany sets multiple types at once', function (): void {
    $f = MorphSwitcher::make('subject')->morphMany([
        'post' => TestResourceUserModel::class,
        'user' => TestResourceUserModel::class,
    ]);

    expect(array_keys($f->getMorphTypes()))->toBe(['post', 'user']);
});

it('toArray includes morphTypes for MorphSwitcher', function (): void {
    $f = MorphSwitcher::make('s')->morph('a', TestResourceUserModel::class);
    $arr = $f->toArray();
    expect($arr['type'])->toBe('morph_switcher');
    expect($arr['attributes']['morphTypes'])->toHaveKey('a');
});
