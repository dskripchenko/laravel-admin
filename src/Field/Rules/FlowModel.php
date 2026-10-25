<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field\Rules;

use Closure;
use Dskripchenko\LaravelAdmin\Support\FlowGraph;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value is a @dskripchenko/flow model — an object or its JSON — of the
 * right shape: see FlowGraph. With $types, every node's type must be one of
 * them (inline sub-processes included).
 *
 * An empty value passes: whether the field is required is up to `required`.
 *
 *     'process' => ['required', new FlowModel],
 *     'process' => [new FlowModel(['start', 'state', 'end'])],
 */
final class FlowModel implements ValidationRule
{
    /**
     * @param  list<string>|null  $types  The node types allowed; any when null.
     */
    public function __construct(private readonly ?array $types = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $decoded = FlowGraph::decode($value);
        if (is_string($decoded)) {
            $fail((string) __('Модель процесса должна быть объектом JSON.'));

            return;
        }

        $errors = FlowGraph::errors($decoded, $this->types);
        if ($errors !== []) {
            $fail((string) __('Некорректная модель процесса: :error', ['error' => $errors[0]]));
        }
    }
}
