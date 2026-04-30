<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\BuiltIn\ForceDeleteAction;
use Dskripchenko\LaravelAdmin\Action\BuiltIn\ImpersonateAction;
use Dskripchenko\LaravelAdmin\Action\BuiltIn\ReplicateAction;
use Dskripchenko\LaravelAdmin\Action\BuiltIn\RestoreAction;

it('RestoreAction returns Button with row position + restore permission', function (): void {
    $arr = RestoreAction::for('admin.posts')->toArray();
    expect($arr['type'])->toBe('button');
    expect($arr['name'])->toBe('restore');
    expect($arr['label'])->toBe('Восстановить');
    expect($arr['position'])->toBe(['row']);
    expect($arr['permission'])->toBe('admin.posts.restore');
    expect($arr['icon'])->toBe('rotate-ccw');
    expect($arr['attributes']['method'])->toBe('restore');
});

it('ForceDeleteAction marks destructive + confirm', function (): void {
    $arr = ForceDeleteAction::for('admin.posts')->toArray();
    expect($arr['name'])->toBe('forceDelete');
    expect($arr['permission'])->toBe('admin.posts.force-delete');
    expect($arr['destructive'])->toBeTrue();
    expect($arr['confirm']['title'])->toBe('Окончательное удаление');
    expect($arr['confirm']['message'])->toContain('невозможно');
});

it('ReplicateAction has replicate permission and method', function (): void {
    $arr = ReplicateAction::for('admin.posts')->toArray();
    expect($arr['name'])->toBe('replicate');
    expect($arr['permission'])->toBe('admin.posts.replicate');
    expect($arr['attributes']['method'])->toBe('replicate');
});

it('ImpersonateAction default permission falls back to config', function (): void {
    config()->set('admin.auth.impersonation.permission', 'admin.impersonate');
    $arr = ImpersonateAction::make()->toArray();
    expect($arr['permission'])->toBe('admin.impersonate');
    expect($arr['attributes']['method'])->toBe('startImpersonation');
    expect($arr['confirm'])->not->toBeNull();
});

it('ImpersonateAction allows custom permission override', function (): void {
    $arr = ImpersonateAction::make('custom.impersonate.scope')->toArray();
    expect($arr['permission'])->toBe('custom.impersonate.scope');
});

it('all built-in row actions are positioned for row', function (): void {
    foreach ([
        RestoreAction::for('admin.x'),
        ForceDeleteAction::for('admin.x'),
        ReplicateAction::for('admin.x'),
        ImpersonateAction::make(),
    ] as $action) {
        expect($action->toArray()['position'])->toBe(['row']);
    }
});
