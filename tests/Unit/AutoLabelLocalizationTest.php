<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\ModalAction;
use Dskripchenko\LaravelAdmin\Field\Builder;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Label;
use Dskripchenko\LaravelAdmin\Field\Slider;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\I18n\AutoLabel;
use Dskripchenko\LaravelAdmin\Infolist\TextEntry;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdmin\Widget\GaugeWidget;
use Illuminate\Support\Facades\Lang;

/**
 * Labels made from a name, and captions that used to skip the translation:
 * in a Russian panel they read in Russian, in an English one in English.
 */
it('gives the common column names a caption of the source language', function (): void {
    app()->setLocale('ru');

    expect(TableColumn::make('created_at')->toArray()['label'])->toBe('Создано');
    expect(TableColumn::make('is_active')->toArray()['label'])->toBe('Активен');
    expect(InputFilter::for('status')->toArray()['label'])->toBe('Статус');
    expect(Input::make('locale')->toArray()['label'])->toBe('Язык');
    expect(TextEntry::make('updated_at')->toArray()['label'])->toBe('Обновлено');
    expect(TableColumn::make('id')->toArray()['label'])->toBe('ID');
});

it('translates those captions for an English panel', function (): void {
    app()->setLocale('en');

    expect(TableColumn::make('created_at')->toArray()['label'])->toBe('Created');
    expect(TableColumn::make('deleted_at')->toArray()['label'])->toBe('Deleted');
    expect(InputFilter::for('is_active')->toArray()['label'])->toBe('Active');
});

it('keeps making other names readable', function (): void {
    expect(AutoLabel::sentence('opens_at'))->toBe('Opens at');
    expect(AutoLabel::headline('opens_at'))->toBe('Opens At');
    expect(AutoLabel::headline(''))->toBe('');
});

it('translates a modal action\'s title and submit label', function (): void {
    Lang::addLines(['*.Перенос доставки' => 'Move the delivery', '*.Перенести' => 'Reschedule'], 'en');
    app()->setLocale('en');

    $arr = ModalAction::make('Перенести')->modalTitle('Перенос доставки')->submitLabel('Перенести')->toArray();

    expect($arr['attributes']['modalTitle'])->toBe('Move the delivery');
    expect($arr['attributes']['submitLabel'])->toBe('Reschedule');
});

it('translates builder block labels, slider marks, a label\'s static text and a gauge unit', function (): void {
    // Lang::addLines() reads a dot as nesting, so the keys here have none.
    Lang::addLines([
        '*.Цитата' => 'Quote',
        '*.Выкл' => 'Off',
        '*.Макс' => 'Max',
        '*.Только для чтения' => 'Read-only',
        '*.штук' => 'pcs',
    ], 'en');
    app()->setLocale('en');

    $builder = Builder::make('body')->block('quote', [Input::make('text')], 'Цитата')->toArray();
    expect($builder['attributes']['blocks']['quote']['label'])->toBe('Quote');

    $slider = Slider::make('level')->marks([0 => 'Выкл', 100 => 'Макс'])->toArray();
    expect($slider['attributes']['marks'])->toBe([0 => 'Off', 100 => 'Max']);

    expect(Label::make('note')->value('Только для чтения')->toArray()['attributes']['value'])->toBe('Read-only');
    // A text from the state is data: default() is not translated.
    expect(Label::make('note')->default('Только для чтения')->toArray()['defaultValue'])->toBe('Только для чтения');

    expect(GaugeWidget::make()->unit('штук')->data()['unit'])->toBe('pcs');
});
