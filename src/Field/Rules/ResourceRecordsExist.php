<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field\Rules;

use Closure;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Every key a ResourcePicker submits names a record of the target resource's
 * indexQuery() — the same set the picker dialog lists. A host that scopes the
 * index (per tenant, per owner) gets the same scope on what may be attached.
 *
 * An empty value passes: whether the field is required is up to `required`.
 */
final class ResourceRecordsExist implements ValidationRule
{
    public function __construct(
        private readonly string $slug,
        private readonly bool $multiple = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        if (is_array($value) !== $this->multiple || (is_array($value) && ! array_is_list($value))) {
            $fail((string) __('Выбрана недопустимая запись.'));

            return;
        }

        $keys = is_array($value) ? $value : [$value];
        foreach ($keys as $key) {
            if (! is_int($key) && ! (is_string($key) && $key !== '')) {
                $fail((string) __('Выбрана недопустимая запись.'));

                return;
            }
        }

        $resource = app(ResourceRegistry::class)->resolve($this->slug);
        if ($resource === null) {
            $fail((string) __('Ресурс :resource не зарегистрирован.', ['resource' => $this->slug]));

            return;
        }

        $wanted = array_values(array_unique(array_map('strval', $keys)));
        $query = $resource->indexQuery();
        $found = $query->whereKey($wanted)
            ->pluck($query->getModel()->getQualifiedKeyName())
            ->map(static fn (mixed $k): string => is_scalar($k) ? (string) $k : '')
            ->unique()
            ->all();

        if (count($found) !== count($wanted)) {
            $fail((string) __('Выбранная запись не найдена или недоступна.'));
        }
    }
}
