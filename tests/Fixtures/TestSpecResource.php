<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\DatePicker;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Field\Switcher;
use Dskripchenko\LaravelAdmin\Field\TagsInput;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Validation\Rule;

/**
 * A resource with one field of every common kind, for the OpenAPI spec tests.
 *
 * @internal
 */
final class TestSpecResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public function fields(): array
    {
        return [
            Input::make('title')->required()->maxlength(120)->title('Title'),
            Input::make('email')->type('email'),
            Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])->required(),
            Select::make('labels')->options(['red' => 'Red', 'green' => 'Green'])->multiple(),
            Number::make('price')->min(0)->max(1000),
            Number::make('quantity')->integer(),
            Switcher::make('active')->default(true),
            DatePicker::make('published_on'),
            TagsInput::make('keywords'),
            Input::make('code')->rules(['nullable', Rule::in(['a1', 'b2'])]),
            Input::make('secret')->required()->onUpdate(false),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('title')->sort()->search(),
            TableColumn::make('status'),
        ];
    }

    public function filters(): array
    {
        return [InputFilter::for('title')];
    }

    public function actions(): array
    {
        return [Button::make('Publish')->method('publish')];
    }
}
