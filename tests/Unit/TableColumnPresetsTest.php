<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Table\TableColumn;

it('asDate stores preset=date with format', function (): void {
    $arr = TableColumn::make('created_at')->asDate('d.m.Y')->toArray();
    expect($arr['preset'])->toBe('date');
    expect($arr['type'])->toBe('date');
    expect($arr['meta']['format'])->toBe('d.m.Y');
});

it('asDateTime defaults format to d.m.Y H:i:s', function (): void {
    $arr = TableColumn::make('updated_at')->asDateTime()->toArray();
    expect($arr['preset'])->toBe('datetime');
    expect($arr['meta']['format'])->toBe('d.m.Y H:i:s');
});

it('asMoney stores currency + decimals', function (): void {
    $arr = TableColumn::make('price')->asMoney('USD', 2)->toArray();
    expect($arr['preset'])->toBe('money');
    expect($arr['meta'])->toBe(['currency' => 'USD', 'decimals' => 2]);
});

it('asBoolean stores labels (or null defaults)', function (): void {
    $arr = TableColumn::make('is_active')->asBoolean('Да', 'Нет')->toArray();
    expect($arr['preset'])->toBe('boolean');
    expect($arr['meta'])->toBe(['trueLabel' => 'Да', 'falseLabel' => 'Нет']);
});

it('asBytes preset name is bytes', function (): void {
    expect(TableColumn::make('size')->asBytes()->toArray()['preset'])->toBe('bytes');
});

it('asBadge stores color map', function (): void {
    $col = TableColumn::make('status')->asBadge([
        'active' => 'green',
        'banned' => 'red',
    ]);
    $meta = $col->toArray()['meta'];
    expect($meta['colors'])->toBe(['active' => 'green', 'banned' => 'red']);
});

it('asLink with string template', function (): void {
    $arr = TableColumn::make('email')
        ->asLink('mailto::value', '_blank')
        ->toArray();
    expect($arr['preset'])->toBe('link');
    expect($arr['meta']['template'])->toBe('mailto::value');
    expect($arr['meta']['target'])->toBe('_blank');
});

it('asLink with callable stores hrefFn (not template)', function (): void {
    $arr = TableColumn::make('id')
        ->asLink(fn ($v, $row) => "/users/{$v}")
        ->toArray();
    expect($arr['meta']['template'] ?? null)->toBeNull();
    expect($arr['meta']['hrefFn'])->toBeCallable();
});

it('asImage stores width/height meta', function (): void {
    $arr = TableColumn::make('avatar')->asImage(64, 64)->toArray();
    expect($arr['preset'])->toBe('image');
    expect($arr['meta'])->toBe(['width' => 64, 'height' => 64]);
});

it('format(callable) is applied via applyFormatter', function (): void {
    $col = TableColumn::make('status')->format(
        fn ($value, $row) => $value === 'active' ? '✓' : '✗',
    );

    expect($col->hasFormatter())->toBeTrue();
    expect($col->applyFormatter('active', []))->toBe('✓');
    expect($col->applyFormatter('banned', []))->toBe('✗');
});

it('format receives $row as second argument', function (): void {
    $col = TableColumn::make('full_name')->format(
        fn ($value, array $row) => $row['first'].' '.$row['last'],
    );

    expect($col->applyFormatter(null, ['first' => 'John', 'last' => 'Doe']))
        ->toBe('John Doe');
});

it('column without format() passes value through unchanged', function (): void {
    $col = TableColumn::make('x');
    expect($col->hasFormatter())->toBeFalse();
    expect($col->applyFormatter(42, []))->toBe(42);
});
