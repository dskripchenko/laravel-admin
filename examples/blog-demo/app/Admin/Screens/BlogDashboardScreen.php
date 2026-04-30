<?php

declare(strict_types=1);

namespace App\Admin\Screens;

use App\Models\Article;
use App\Models\Category;
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\MarkdownWidget;
use Dskripchenko\LaravelAdmin\Widget\RecentListWidget;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;

final class BlogDashboardScreen extends DashboardScreen
{
    public function name(): string
    {
        return 'Blog Dashboard';
    }

    public function permission(): array|string|null
    {
        return 'admin.articles.view';
    }

    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->title('Статистика')
                ->size(12)
                ->stat('Статей всего', Article::count(), 'blue', 'file-text')
                ->stat('Опубликовано', Article::where('is_published', true)->count(), 'green', 'check')
                ->stat('Черновиков', Article::where('is_published', false)->count(), 'gray', 'edit')
                ->stat('Рубрик', Category::count(), 'purple', 'folder'),

            RecentListWidget::make()
                ->title('Последние статьи')
                ->size(8)
                ->model(Article::class)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->column('title', 'Заголовок')
                ->column('created_at', 'Создана')
                ->linkTo('articles'),

            MarkdownWidget::make()
                ->title('Подсказки')
                ->size(4)
                ->content(<<<'MD'
                    ### Полезные ссылки

                    - [Документация](/admin/docs)
                    - [API Reference](/api/admin/doc)
                    - Поддержка: support@example.com
                    MD),
        ];
    }
}
