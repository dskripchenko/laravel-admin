<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Select;
use Illuminate\Support\Facades\Lang;

/**
 * BL-11: пользовательские строки переводятся при сериализации через
 * JSON-переводы (ключ — исходная строка) — host не оборачивает лейблы в __().
 */
it('переводит label/help/placeholder и options при сериализации поля', function (): void {
    Lang::addLines([
        '*.Название' => 'Name',
        '*.Подсказка' => 'Hint',
        '*.Введите текст' => 'Enter text',
        '*.Да' => 'Yes',
    ], 'en');
    app()->setLocale('en');

    $field = Input::make('title')->title('Название')->help('Подсказка')->placeholder('Введите текст');
    $arr = $field->toArray();

    expect($arr['label'])->toBe('Name');
    expect($arr['help'])->toBe('Hint');
    expect($arr['placeholder'])->toBe('Enter text');
    expect($arr['attributes']['title'])->toBe('Name');

    $select = Select::make('flag')->options([['value' => 1, 'label' => 'Да']]);
    $opts = $select->toArray()['attributes']['options'];
    expect($opts[0]['label'])->toBe('Yes');
});

it('строки без перевода остаются как есть (идемпотентность)', function (): void {
    app()->setLocale('en');

    $arr = Input::make('x')->title('Непереведённая строка')->toArray();

    expect($arr['label'])->toBe('Непереведённая строка');
});
