<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout\BlockTree;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Checks a block tree — a list of nodes `{ref, type, data, children}` — against
 * the types of a BlockTree layout, before a screen method saves or publishes it:
 *
 *   - the shape: a list of objects, a scalar `ref` unique in the tree, `data`
 *     an object or null, `children` a list;
 *   - the types: every `type` is one of the described ones;
 *   - the nesting: a top-level node is a root type, a child is one its
 *     parent accepts, the depth is within maxDepth;
 *   - the data (unless `$data` is false): the rules of the type's fields.
 *
 * Each problem is reported with the node's ref and a path like `0.children.2`,
 * so the panel can mark the node:
 *
 *     ['ref' => 15, 'path' => '0.children.2', 'field' => 'title', 'message' => '...']
 *
 * The BlockTree layout builds one with its own types: `$layout->validator()`.
 */
final class TreeValidator
{
    /** @var array<string, BlockType> */
    private array $types = [];

    /**
     * @param  iterable<BlockType>  $types
     * @param  list<string>|null  $rootTypes  The types allowed at the top level; null — those marked root().
     */
    public function __construct(iterable $types, private readonly ?array $rootTypes = null, private readonly ?int $maxDepth = null)
    {
        foreach ($types as $type) {
            $this->types[$type->type()] = $type;
        }
    }

    /**
     * @param  mixed  $nodes  The tree as it came from the request.
     * @return list<array{ref: int|string|null, path: string, field: string|null, message: string}>
     */
    public function errors(mixed $nodes, bool $data = true): array
    {
        $errors = [];
        if (! is_array($nodes) || ! array_is_list($nodes)) {
            return [self::error(null, '', null, __('Дерево блоков должно быть списком узлов'))];
        }

        $seen = [];
        $this->walk($nodes, null, true, '', 1, $data, $seen, $errors);

        return $errors;
    }

    public function passes(mixed $nodes, bool $data = true): bool
    {
        return $this->errors($nodes, $data) === [];
    }

    /**
     * Throws a ValidationException when the tree is wrong: its messages are
     * keyed `{$key}.{path}` (`{$key}.{path}.data.{field}` for the data), and
     * the panel finds the node by its path.
     *
     *
     * @throws ValidationException
     */
    public function validate(mixed $nodes, string $key = 'nodes', bool $data = true): void
    {
        $errors = $this->errors($nodes, $data);
        if ($errors === []) {
            return;
        }
        $messages = [];
        foreach ($errors as $error) {
            $name = $key.($error['path'] !== '' ? '.'.$error['path'] : '').($error['field'] !== null ? '.data.'.$error['field'] : '');
            $messages[$name][] = $error['message'];
        }

        throw ValidationException::withMessages($messages);
    }

    /**
     * @param  list<mixed>  $nodes
     * @param  bool  $placed  Whether the parent is known, so the placement can be checked.
     * @param  array<array-key, true>  $seen
     * @param  list<array{ref: int|string|null, path: string, field: string|null, message: string}>  $errors
     */
    private function walk(array $nodes, ?BlockType $parent, bool $placed, string $prefix, int $depth, bool $data, array &$seen, array &$errors): void
    {
        foreach ($nodes as $index => $node) {
            $path = $prefix === '' ? (string) $index : $prefix.'.children.'.$index;
            if (! is_array($node)) {
                $errors[] = self::error(null, $path, null, __('Узел дерева должен быть объектом'));

                continue;
            }

            $ref = $node['ref'] ?? null;
            if (! is_int($ref) && (! is_string($ref) || $ref === '')) {
                $errors[] = self::error(null, $path, null, __('У узла нет идентификатора (ref)'));
                $ref = null;
            } elseif (isset($seen[(string) $ref])) {
                $errors[] = self::error($ref, $path, null, __('Идентификатор :ref повторяется в дереве', ['ref' => (string) $ref]));
            } else {
                $seen[(string) $ref] = true;
            }

            $typeName = $node['type'] ?? null;
            $type = is_string($typeName) ? ($this->types[$typeName] ?? null) : null;
            if ($type === null) {
                $errors[] = self::error($ref, $path, null, __('Неизвестный тип блока «:type»', ['type' => is_scalar($typeName) ? (string) $typeName : '']));
            } elseif (! $placed) {
                // Inside a block of an unknown type: its own error says enough.
            } elseif ($parent === null && ! $this->isRoot($type)) {
                $errors[] = self::error($ref, $path, null, __('Блок «:type» нельзя размещать на верхнем уровне', ['type' => $type->toArray()['label']]));
            } elseif ($parent !== null && ! $parent->allows($type->type())) {
                $errors[] = self::error($ref, $path, null, __('Блок «:type» нельзя вкладывать в «:parent»', [
                    'type' => $type->toArray()['label'],
                    'parent' => $parent->toArray()['label'],
                ]));
            }

            if ($this->maxDepth !== null && $depth > $this->maxDepth) {
                $errors[] = self::error($ref, $path, null, __('Превышена глубина вложенности (не больше :max)', ['max' => $this->maxDepth]));
            }

            $values = $node['data'] ?? null;
            if ($values !== null && ! is_array($values)) {
                $errors[] = self::error($ref, $path, null, __('Данные блока должны быть объектом'));
                $values = null;
            }
            if ($data && $type !== null) {
                $this->checkData($type, $values ?? [], $ref, $path, $errors);
            }

            $children = $node['children'] ?? [];
            if (! is_array($children) || ! array_is_list($children)) {
                $errors[] = self::error($ref, $path, null, __('Вложенные блоки должны быть списком'));

                continue;
            }
            // A child of a leaf is reported by its own placement check.
            $this->walk($children, $type, $type !== null, $path, $depth + 1, $data, $seen, $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<array{ref: int|string|null, path: string, field: string|null, message: string}>  $errors
     */
    private function checkData(BlockType $type, array $values, int|string|null $ref, string $path, array &$errors): void
    {
        $rules = $type->rules();
        if ($rules === []) {
            return;
        }
        $validator = Validator::make($values, $rules, [], $type->attributeNames());
        if (! $validator->fails()) {
            return;
        }
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = self::error($ref, $path, (string) $field, (string) $message);
            }
        }
    }

    private function isRoot(BlockType $type): bool
    {
        return $this->rootTypes === null ? $type->isRoot() : in_array($type->type(), $this->rootTypes, true);
    }

    /**
     * @return array{ref: int|string|null, path: string, field: string|null, message: string}
     */
    private static function error(int|string|null $ref, string $path, ?string $field, mixed $message): array
    {
        return ['ref' => $ref, 'path' => $path, 'field' => $field, 'message' => (string) $message];
    }
}
