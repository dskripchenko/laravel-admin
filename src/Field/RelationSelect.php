<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;
use Illuminate\Database\Eloquent\Model;

/**
 * Селектор связи BelongsTo / BelongsToMany.
 *
 * Под капотом — обычный Select, но с привязкой к Eloquent-модели:
 *  - relatedModel — class-string<Model>, чьи записи попадают в options;
 *  - displayColumn — колонка для label;
 *  - valueColumn — колонка для value (по умолчанию 'id');
 *  - searchColumns — колонки для server-side search (?q=...);
 *  - preload — список eager-loaded relations при подгрузке options.
 *
 * SPA шлёт ?q=...&page=... на endpoint resource'а (фаза P5+ может добавить
 * отдельный controller-action `options` — пока оставляем за реализатором).
 */
final class RelationSelect extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'relation_select';
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function relation(string $model, string $displayColumn = 'name', string $valueColumn = 'id'): static
    {
        $this->attributes['relatedModel'] = $model;
        $this->attributes['displayColumn'] = $displayColumn;
        $this->attributes['valueColumn'] = $valueColumn;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function searchable(array $columns): static
    {
        $this->attributes['searchColumns'] = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $relations
     */
    public function preload(array $relations): static
    {
        $this->attributes['preload'] = $relations;

        return $this;
    }

    /**
     * Подгрузить options сразу (для small datasets — справочники).
     * Use with caution: для больших таблиц предпочесть SPA-запрос options.
     */
    public function eager(int $limit = 100): static
    {
        $model = $this->getAttribute('relatedModel');
        if (! is_string($model) || ! class_exists($model)) {
            return $this;
        }

        $valueColumn = (string) ($this->getAttribute('valueColumn') ?? 'id');
        $displayColumn = (string) ($this->getAttribute('displayColumn') ?? 'name');
        $preload = (array) ($this->getAttribute('preload') ?? []);

        /** @var class-string<Model> $model */
        $query = $model::query();
        if ($preload !== []) {
            $query->with($preload);
        }

        $records = $query->limit($limit)->get([$valueColumn, $displayColumn]);
        $items = $records->map(static fn (Model $m): array => [
            'value' => $m->getAttribute($valueColumn),
            'label' => (string) $m->getAttribute($displayColumn),
        ])->all();

        $this->attributes['choices'] = $items;

        return $this;
    }
}
