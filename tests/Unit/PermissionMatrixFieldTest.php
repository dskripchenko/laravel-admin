<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\PermissionMatrix;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;

it('serializes the groups as rows with the extra column', function (): void {
    $arr = PermissionMatrix::make('permission_keys')
        ->title('Права')
        ->groups([
            'Клиенты › Очереди' => [
                'clients.claims' => 'Обращения и претензии',
                'clients.callbacks' => 'Обратные звонки',
            ],
        ])
        ->extraColumn('ПДн без маски', ['clients.claims' => 'clients.claims.reveal'])
        ->toArray();

    expect($arr['type'])->toBe('permission_matrix')
        ->and($arr['attributes']['extraLabel'])->toBe('ПДн без маски')
        ->and($arr['attributes']['groups'])->toBe([[
            'label' => 'Клиенты › Очереди',
            'items' => [
                ['key' => 'clients.claims', 'label' => 'Обращения и претензии', 'hint' => null, 'extra' => 'clients.claims.reveal'],
                ['key' => 'clients.callbacks', 'label' => 'Обратные звонки', 'hint' => null, 'extra' => null],
            ],
        ]]);
});

it('takes the list shape, a closure and the registry, resolved only when serialized', function (): void {
    $calls = 0;
    $field = PermissionMatrix::make('p')->groups(function () use (&$calls): array {
        $calls++;

        return [['label' => 'Служебные разделы', 'items' => [['key' => 'platform.roles', 'label' => 'Роли', 'hint' => 'Кто что может']]]];
    });
    expect($calls)->toBe(0);

    app(PermissionRegistry::class)->add(ItemPermission::group('Отчёты сайта')->addPermission('reports.view', 'Просмотр'));
    $groups = $field->fromRegistry()->toArray()['attributes']['groups'];

    expect($calls)->toBeGreaterThan(0)
        ->and(array_column($groups, 'label'))->toContain('Служебные разделы', 'Отчёты сайта')
        ->and($groups[0]['items'][0]['hint'])->toBe('Кто что может')
        ->and($field->keys())->toContain('platform.roles', 'reports.view');
});
