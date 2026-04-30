<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedCreateScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedEditScreen;
use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedListScreen;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // Гарантируем, что таблица под TestResourceUserModel создана.
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }
});

it('GeneratedListScreen compile() returns generated.list type + columns/filters', function (): void {
    $screen = new GeneratedListScreen(new TestUserResource);
    $compiled = $screen->compile();

    expect($compiled['type'])->toBe('generated.list');
    expect($compiled['resource_slug'])->toBe('test-users');
    expect($compiled['name'])->toBeString();
    expect($compiled['layout'])->toBeArray();
    expect($compiled['state'])->toHaveKey('columns');
    expect($compiled['state'])->toHaveKey('filters');
    expect($compiled['state'])->toHaveKey('searchable');
});

it('GeneratedListScreen has Создать button in commandBar', function (): void {
    $screen = new GeneratedListScreen(new TestUserResource);
    $compiled = $screen->compile();

    expect($compiled['command_bar'])->not->toBeEmpty();
    expect($compiled['command_bar'][0]['label'])->toBe('Создать');
});

it('GeneratedListScreen instanceSlug returns {resource}.list', function (): void {
    $screen = new GeneratedListScreen(new TestUserResource);

    expect($screen->instanceSlug())->toBe('test-users.list');
});

it('GeneratedListScreen permission() = resource.permission.view', function (): void {
    $screen = new GeneratedListScreen(new TestUserResource);

    expect($screen->permission())->toBe('admin.test-users.view');
});

it('GeneratedCreateScreen compile() returns generated.create + empty record', function (): void {
    $screen = new GeneratedCreateScreen(new TestUserResource);
    $compiled = $screen->compile();

    expect($compiled['type'])->toBe('generated.create');
    expect($compiled['state']['record'])->toBeArray();
    expect($compiled['name'])->toContain('Создать');
});

it('GeneratedCreateScreen permission() = resource.create', function (): void {
    $screen = new GeneratedCreateScreen(new TestUserResource);

    expect($screen->permission())->toBe('admin.test-users.create');
});

it('GeneratedCreateScreen has Сохранить + Отмена in commandBar', function (): void {
    $screen = new GeneratedCreateScreen(new TestUserResource);
    $compiled = $screen->compile();

    $labels = array_map(fn ($a) => $a['label'], $compiled['command_bar']);
    expect($labels)->toContain('Сохранить');
    expect($labels)->toContain('Отмена');
});

it('GeneratedEditScreen compile() loads record by id', function (): void {
    $record = TestResourceUserModel::create(['name' => 'Edit Me', 'email' => 'em@example.com']);

    $screen = new GeneratedEditScreen(new TestUserResource);
    $compiled = $screen->compile($record->id);

    expect($compiled['type'])->toBe('generated.edit');
    expect($compiled['state']['record']['name'])->toBe('Edit Me');
    expect($compiled['state']['id'])->toBe($record->id);
});

it('GeneratedEditScreen 404 when record not found', function (): void {
    $screen = new GeneratedEditScreen(new TestUserResource);

    expect(fn () => $screen->compile(99999))
        ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

it('GeneratedEditScreen permission() = resource.update', function (): void {
    $screen = new GeneratedEditScreen(new TestUserResource);

    expect($screen->permission())->toBe('admin.test-users.update');
});

it('GeneratedEditScreen commandBar has Сохранить, Удалить, Назад', function (): void {
    $screen = new GeneratedEditScreen(new TestUserResource);
    $compiled = $screen->compile();

    $labels = array_map(fn ($a) => $a['label'], $compiled['command_bar']);
    expect($labels)->toContain('Сохранить');
    expect($labels)->toContain('Удалить');
    expect($labels)->toContain('Назад');
});
