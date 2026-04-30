<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Illuminate\Database\Eloquent\Model;

/**
 * Селектор для morphTo-связи: type + id.
 *
 * SPA рендерит как два связанных Select'а:
 *   1. Type select (из заявленного списка morph-моделей).
 *   2. ID select (зависит от type, через тот же endpoint что RelationSelect).
 *
 * Сериализуется в state как `{type: 'App\Post', id: 42}`.
 *
 * Резолвит alias через morph map (`Relation::enforceMorphMap`); если alias не
 * объявлен — использует FQCN модели как type.
 */
final class MorphSwitcher extends Field
{
    /** @var array<string, array{model: class-string<Model>, displayColumn: string, valueColumn: string}> */
    private array $morphTypes = [];

    public function fieldType(): string
    {
        return 'morph_switcher';
    }

    /**
     * Зарегистрировать morph-тип.
     *
     * @param  class-string<Model>  $model
     */
    public function morph(string $alias, string $model, string $displayColumn = 'name', string $valueColumn = 'id'): static
    {
        $this->morphTypes[$alias] = [
            'model' => $model,
            'displayColumn' => $displayColumn,
            'valueColumn' => $valueColumn,
        ];
        $this->attributes['morphTypes'] = $this->morphTypes;

        return $this;
    }

    /**
     * Зарегистрировать сразу несколько morph-типов.
     *
     * @param  array<string, class-string<Model>>  $map  alias => model
     */
    public function morphMany(array $map): static
    {
        foreach ($map as $alias => $model) {
            $this->morph($alias, $model);
        }

        return $this;
    }

    /**
     * @return array<string, array{model: class-string<Model>, displayColumn: string, valueColumn: string}>
     */
    public function getMorphTypes(): array
    {
        return $this->morphTypes;
    }
}
