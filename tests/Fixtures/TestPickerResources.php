<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\ResourcePicker;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The records a ResourcePicker picks from, for the picker tests.
 *
 * @internal
 */
final class TestPickerPhoto extends Model
{
    protected $table = 'picker_photos';

    protected $guarded = [];
}

/**
 * A target resource with previews; its index hides the `private` photos.
 *
 * @internal
 */
final class TestPickerPhotoResource extends Resource
{
    public static string $model = TestPickerPhoto::class;

    public function fields(): array
    {
        return [Input::make('name')];
    }

    public function columns(): array
    {
        return [TableColumn::make('id'), TableColumn::make('name')->search()];
    }

    public function filters(): array
    {
        return [InputFilter::for('album')];
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->where('private', false);
    }

    public function pickerPreview(Model $row): ?string
    {
        return '/img/'.$row->getKey().'.png';
    }
}

/**
 * @internal
 */
final class TestPickerPost extends Model
{
    protected $table = 'picker_posts';

    protected $guarded = [];

    protected $casts = ['gallery' => 'array'];
}

/**
 * A host resource holding a single and a multiple picker.
 *
 * @internal
 */
final class TestPickerPostResource extends Resource
{
    public static string $model = TestPickerPost::class;

    public function fields(): array
    {
        return [
            Input::make('title'),
            ResourcePicker::make('cover_id')->resource(TestPickerPhotoResource::class),
            ResourcePicker::make('gallery')->resource('test-picker-photos')->multiple()->maxItems(3),
        ];
    }
}
