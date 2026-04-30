<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Layout\Layout;

/**
 * Smoke-regression tests: для каждого concrete Field/Layout/Action
 * проверяем что класс инстанцируется + toArray() не бросает.
 *
 * Не покрывает business-логику (для этого есть точечные тесты per-class),
 * но ловит regression'ы: переименованный родительский метод, опечатка
 * сигнатуры, breaking-change в зависимостях.
 */

/**
 * @return list<class-string<Field>>
 */
function admin_field_classes(): array
{
    return [
        Dskripchenko\LaravelAdmin\Field\Builder::class,
        Dskripchenko\LaravelAdmin\Field\Cascader::class,
        Dskripchenko\LaravelAdmin\Field\Checkbox::class,
        Dskripchenko\LaravelAdmin\Field\Code::class,
        Dskripchenko\LaravelAdmin\Field\ColorPicker::class,
        Dskripchenko\LaravelAdmin\Field\Combobox::class,
        Dskripchenko\LaravelAdmin\Field\DatePicker::class,
        Dskripchenko\LaravelAdmin\Field\DateRange::class,
        Dskripchenko\LaravelAdmin\Field\FileUpload::class,
        Dskripchenko\LaravelAdmin\Field\Group::class,
        Dskripchenko\LaravelAdmin\Field\Hidden::class,
        Dskripchenko\LaravelAdmin\Field\ImageCropper::class,
        Dskripchenko\LaravelAdmin\Field\Input::class,
        Dskripchenko\LaravelAdmin\Field\KeyValue::class,
        Dskripchenko\LaravelAdmin\Field\Label::class,
        Dskripchenko\LaravelAdmin\Field\Markdown::class,
        Dskripchenko\LaravelAdmin\Field\MorphSwitcher::class,
        Dskripchenko\LaravelAdmin\Field\Number::class,
        Dskripchenko\LaravelAdmin\Field\Password::class,
        Dskripchenko\LaravelAdmin\Field\Radio::class,
        Dskripchenko\LaravelAdmin\Field\Rating::class,
        Dskripchenko\LaravelAdmin\Field\RelationSelect::class,
        Dskripchenko\LaravelAdmin\Field\RelationTable::class,
        Dskripchenko\LaravelAdmin\Field\Repeater::class,
        Dskripchenko\LaravelAdmin\Field\Select::class,
        Dskripchenko\LaravelAdmin\Field\Slider::class,
        Dskripchenko\LaravelAdmin\Field\Slug::class,
        Dskripchenko\LaravelAdmin\Field\Switcher::class,
        Dskripchenko\LaravelAdmin\Field\TagsInput::class,
        Dskripchenko\LaravelAdmin\Field\Textarea::class,
        Dskripchenko\LaravelAdmin\Field\TimePicker::class,
        Dskripchenko\LaravelAdmin\Field\TranslatableInput::class,
        Dskripchenko\LaravelAdmin\Field\TreeSelect::class,
        Dskripchenko\LaravelAdmin\Field\Wysiwyg::class,
    ];
}

it('все Field-классы инстанцируются + toArray возвращает stable shape', function (string $class): void {
    $field = $class::make('test_field');
    expect($field)->toBeInstanceOf(Field::class);
    expect($field->name())->toBe('test_field');
    expect($field->fieldType())->toBeString();
    expect($field->fieldType())->not->toBeEmpty();

    $arr = $field->toArray();
    expect($arr)->toHaveKeys(['kind', 'name', 'type', 'label', 'required', 'rules', 'options', 'visibility', 'attributes']);
    expect($arr['kind'])->toBe('field');
    expect($arr['name'])->toBe('test_field');
    expect($arr['type'])->toBe($field->fieldType());
})->with(admin_field_classes());

it('все Field поддерживают canSee + isVisible round-trip', function (string $class): void {
    $field = $class::make('x');
    expect($field->isVisible())->toBeTrue();
    $field->canSee(false);
    expect($field->isVisible())->toBeFalse();
})->with(admin_field_classes());

/**
 * Layout factories: ключ = label, значение = list[class, factory-closure].
 *
 * Pest 3 при array-with-string-keys использует ключ как dataset-label
 * и unwraps значение как arguments — поэтому factory передаём callable'ом.
 *
 * @return array<string, array{0: class-string<Layout>, 1: Closure(): Layout}>
 */
function admin_layout_factories(): array
{
    return [
        'Rows' => [Dskripchenko\LaravelAdmin\Layout\Rows::class, fn () => Dskripchenko\LaravelAdmin\Layout\Rows::make()],
        'Columns' => [Dskripchenko\LaravelAdmin\Layout\Columns::class, fn () => Dskripchenko\LaravelAdmin\Layout\Columns::make()],
        'Tabs' => [Dskripchenko\LaravelAdmin\Layout\Tabs::class, fn () => Dskripchenko\LaravelAdmin\Layout\Tabs::make()],
        'Block' => [Dskripchenko\LaravelAdmin\Layout\Block::class, fn () => Dskripchenko\LaravelAdmin\Layout\Block::make('Title')],
        'View' => [Dskripchenko\LaravelAdmin\Layout\View::class, fn () => Dskripchenko\LaravelAdmin\Layout\View::make('admin.test')],
        'Accordion' => [Dskripchenko\LaravelAdmin\Layout\Accordion::class, fn () => Dskripchenko\LaravelAdmin\Layout\Accordion::make()],
        'Modal' => [Dskripchenko\LaravelAdmin\Layout\Modal::class, fn () => Dskripchenko\LaravelAdmin\Layout\Modal::make('M')],
        'Drawer' => [Dskripchenko\LaravelAdmin\Layout\Drawer::class, fn () => Dskripchenko\LaravelAdmin\Layout\Drawer::make('D')],
        'Wrapper' => [Dskripchenko\LaravelAdmin\Layout\Wrapper::class, fn () => Dskripchenko\LaravelAdmin\Layout\Wrapper::make()],
        'Wizard' => [Dskripchenko\LaravelAdmin\Layout\Wizard::class, fn () => Dskripchenko\LaravelAdmin\Layout\Wizard::make()],
        'Step' => [Dskripchenko\LaravelAdmin\Layout\Step::class, fn () => Dskripchenko\LaravelAdmin\Layout\Step::make('S')],
        'Infolist' => [Dskripchenko\LaravelAdmin\Layout\Infolist::class, fn () => Dskripchenko\LaravelAdmin\Layout\Infolist::make()],
        'Dashboard' => [Dskripchenko\LaravelAdmin\Layout\Dashboard::class, fn () => Dskripchenko\LaravelAdmin\Layout\Dashboard::make()],
        'AuditTrail' => [Dskripchenko\LaravelAdmin\Layout\AuditTrail::class, fn () => Dskripchenko\LaravelAdmin\Layout\AuditTrail::for('App\\Models\\User')],
    ];
}

it('все Layout-классы инстанцируются и сериализуются', function (string $class, Closure $factory): void {
    $layout = $factory();
    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout)->toBeInstanceOf($class);
    expect($layout->type())->toBeString();

    $arr = $layout->toArray();
    expect($arr)->toHaveKeys(['id', 'type', 'props', 'children']);
    expect($arr['type'])->toBe($layout->type());
})->with(admin_layout_factories());

/**
 * @return array<string, array{0: class-string<Action>, 1: Closure(): Action}>
 */
function admin_action_factories(): array
{
    return [
        'Button' => [Dskripchenko\LaravelAdmin\Action\Button::class, fn () => Dskripchenko\LaravelAdmin\Action\Button::make('Click')],
        'Link' => [Dskripchenko\LaravelAdmin\Action\Link::class, fn () => Dskripchenko\LaravelAdmin\Action\Link::make('Link')->href('/x')],
        'BulkAction' => [Dskripchenko\LaravelAdmin\Action\BulkAction::class, fn () => Dskripchenko\LaravelAdmin\Action\BulkAction::make('Bulk')],
        'ModalAction' => [Dskripchenko\LaravelAdmin\Action\ModalAction::class, fn () => Dskripchenko\LaravelAdmin\Action\ModalAction::make('Modal')],
        'DropDown' => [Dskripchenko\LaravelAdmin\Action\DropDown::class, fn () => Dskripchenko\LaravelAdmin\Action\DropDown::make('Menu')],
        'AsyncAction' => [Dskripchenko\LaravelAdmin\Action\AsyncAction::class, fn () => Dskripchenko\LaravelAdmin\Action\AsyncAction::make('Async')],
    ];
}

it('все Action-классы инстанцируются и сериализуются', function (string $class, Closure $factory): void {
    $action = $factory();
    expect($action)->toBeInstanceOf(Action::class);
    expect($action)->toBeInstanceOf($class);
    expect($action->type())->toBeString();
    expect($action->name())->toBeString();
    expect($action->name())->not->toBeEmpty();

    $arr = $action->toArray();
    expect($arr)->toHaveKeys(['type', 'name', 'label', 'permission', 'confirm', 'position', 'icon', 'attributes']);
    expect($arr['type'])->toBe($action->type());
})->with(admin_action_factories());

it('Field smoke: каталог содержит все ожидаемые типы', function (): void {
    $types = array_map(
        static fn (string $class): string => $class::make('x')->fieldType(),
        admin_field_classes(),
    );
    expect($types)->toContain('input', 'number', 'select', 'wysiwyg', 'date', 'file', 'tree_select');
});

it('Layout smoke: каталог содержит все ожидаемые типы', function (): void {
    $types = array_map(
        static fn (array $case): string => $case[1]()->type(),
        admin_layout_factories(),
    );
    expect($types)->toContain('rows', 'columns', 'tabs', 'modal', 'wizard', 'infolist', 'dashboard', 'audit_trail');
});
