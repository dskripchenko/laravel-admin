<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Illuminate\Database\Eloquent\Model;

/**
 * The selector of a morphTo relation: a type and an id.
 *
 * The SPA renders two linked selects:
 *   1. The type, out of the declared list of morph models.
 *   2. The id, which depends on the type and goes through the same endpoint as
 *      RelationSelect.
 *
 * In the state it is `{type: 'App\Post', id: 42}`.
 *
 * The alias is resolved through the morph map
 * (`Relation::enforceMorphMap`); with no alias declared, the model's FQCN
 * serves as the type.
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
     * Registers a morph type.
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
     * Registers several morph types at once.
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
