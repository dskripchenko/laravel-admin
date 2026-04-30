<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Display связанной записи через relation на родительской модели.
 *
 * SPA рендерит как ссылку на view-страницу resource'а связанной модели
 * (если linkTo задан) или как простой текст.
 */
final class RelationEntry extends Entry
{
    public function entryType(): string
    {
        return 'relation';
    }

    /**
     * Имя relation на родительской модели.
     */
    public function relation(string $relation): static
    {
        $this->attributes['relation'] = $relation;

        return $this;
    }

    /**
     * Колонка для отображения (default: 'name').
     */
    public function display(string $column): static
    {
        $this->attributes['displayColumn'] = $column;

        return $this;
    }

    /**
     * Resource slug, на который вести по клику.
     */
    public function linkTo(string $resourceSlug): static
    {
        $this->attributes['linkTo'] = $resourceSlug;

        return $this;
    }
}
