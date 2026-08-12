<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The options of a Select, Combobox, Radio, Checkbox or Switch.
 *
 * Inside, `attributes['options']` holds a list<{value, label, disabled?}>. The
 * sources are an associative array, a BackedEnum or an Eloquent model, through
 * Model::all().
 */
trait HasOptions
{
    /**
     * @param  array<int|string, string>|array<int, array{value: mixed, label: string, disabled?: bool}>  $items
     */
    public function options(array $items): static
    {
        $this->attributes['options'] = self::normalizeOptions($items);

        return $this;
    }

    /**
     * Fills the options from an enum class: the value is enum->value for a
     * BackedEnum or enum->name for a UnitEnum, and the label is enum->name.
     *
     * The runtime check is there because calling this with an arbitrary string
     * is conceivable, and static analysis will not stop it.
     *
     * @param  class-string  $enum
     */
    public function fromEnum(string $enum): static
    {
        if (! enum_exists($enum)) {
            throw new \InvalidArgumentException("{$enum} must be an enum");
        }

        $items = [];
        /** @var list<UnitEnum> $cases */
        $cases = $enum::cases();
        foreach ($cases as $case) {
            /** @var mixed $value */
            $value = $case instanceof BackedEnum ? $case->value : $case->name;
            $items[] = ['value' => $value, 'label' => $case->name];
        }
        $this->attributes['options'] = $items;

        return $this;
    }

    /**
     * Fills the options from an Eloquent model or a builder.
     *
     * @param  class-string<Model>|Builder  $source
     */
    public function fromModel(string|Builder $source, string $valueColumn = 'id', string $labelColumn = 'name'): static
    {
        $query = $source instanceof Builder ? $source : $source::query();
        $items = $query->get([$valueColumn, $labelColumn])
            ->map(static fn (Model $m): array => [
                'value' => $m->getAttribute($valueColumn),
                'label' => (string) $m->getAttribute($labelColumn),
            ])
            ->all();

        $this->attributes['options'] = $items;

        return $this;
    }

    /**
     * Multiple choice; it adds the `array` rule during serialization.
     */
    public function multiple(bool $multiple = true): static
    {
        $this->attributes['multiple'] = $multiple;

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return list<array{value: mixed, label: string, disabled?: bool}>
     */
    private static function normalizeOptions(array $items): array
    {
        // Detect already-normalized list of {value, label, ...}.
        if ($items !== [] && array_is_list($items) && is_array($items[0]) && array_key_exists('value', $items[0])) {
            /** @var list<array{value: mixed, label: string, disabled?: bool}> $items */
            return $items;
        }

        $normalized = [];
        foreach ($items as $value => $label) {
            $normalized[] = [
                'value' => $value,
                'label' => is_string($label) ? $label : (string) $value,
            ];
        }

        return $normalized;
    }
}
