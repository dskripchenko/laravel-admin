<?php

declare(strict_types=1);

namespace App\Admin\Resources;

use App\Models\Article;
use App\Models\Category;
use Dskripchenko\LaravelAdmin\Action\BuiltIn\ForceDeleteAction;
use Dskripchenko\LaravelAdmin\Action\BuiltIn\RestoreAction;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\DropDown;
use Dskripchenko\LaravelAdmin\Field\DatePicker;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\RelationSelect;
use Dskripchenko\LaravelAdmin\Field\Slug;
use Dskripchenko\LaravelAdmin\Field\Switcher;
use Dskripchenko\LaravelAdmin\Field\Wysiwyg;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\SelectFromModelFilter;
use Dskripchenko\LaravelAdmin\Filter\SwitcherFilter;
use Dskripchenko\LaravelAdmin\Filter\TrashedFilter;
use Dskripchenko\LaravelAdmin\Infolist\BadgeEntry;
use Dskripchenko\LaravelAdmin\Infolist\RelationEntry;
use Dskripchenko\LaravelAdmin\Infolist\TextEntry;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = Article::class;

    public static string $icon = 'file-text';

    public function fields(): array
    {
        return [
            Input::make('title')->required()->title('Заголовок'),
            Slug::make('slug')->from('title'),
            RelationSelect::make('category_id')
                ->title('Рубрика')
                ->relation(Category::class, 'name', 'id')
                ->searchable(['name'])
                ->required(),
            Wysiwyg::make('body')
                ->preset('default')
                ->uploadImages()
                ->title('Содержимое'),
            Switcher::make('is_published')->title('Опубликовать'),
            DatePicker::make('published_at')
                ->withTime()
                ->title('Дата публикации'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('title')->sort()->search(),
            TableColumn::make('category_id')
                ->label('Рубрика')
                ->format(fn ($id, $row) => Category::find($id)?->name ?? '—'),
            TableColumn::make('is_published')->asBoolean('Да', 'Нет'),
            TableColumn::make('published_at')->asDateTime()->sort(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('title')->label('Поиск по заголовку'),
            SelectFromModelFilter::for('category_id')
                ->fromModel(Category::class, 'name')
                ->label('Рубрика'),
            SwitcherFilter::for('is_published')->label('Опубликована'),
            TrashedFilter::for()->label('Корзина'),
        ];
    }

    public function actions(): array
    {
        return [
            Button::make('Опубликовать')
                ->withName('publish')
                ->method('publish')
                ->position(['row'])
                ->permission(self::permission().'.update')
                ->confirm('Опубликовать статью сейчас?'),

            DropDown::make('Ещё')->items([
                RestoreAction::for(self::permission()),
                ForceDeleteAction::for(self::permission()),
            ]),
        ];
    }

    public function infolist(): array
    {
        return [
            TextEntry::make('title')->label('Заголовок'),
            RelationEntry::make('category')->display('name')->label('Рубрика'),
            BadgeEntry::make('is_published')
                ->label('Статус')
                ->colors(['1' => 'green', '0' => 'gray'])
                ->labels(['1' => 'Опубликована', '0' => 'Черновик']),
            TextEntry::make('published_at')->asDateTime(),
        ];
    }

    public function searchableFields(): array
    {
        return ['title', 'slug'];
    }

    public function with(): array
    {
        return ['category'];
    }

    /**
     * Custom action handler.
     */
    public function publish(Article $article): void
    {
        $article->update([
            'is_published' => true,
            'published_at' => $article->published_at ?? now(),
        ]);
    }
}
