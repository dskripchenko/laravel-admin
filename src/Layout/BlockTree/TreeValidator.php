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
 *   - the data (unless `$data` is false): the rules of the type's fields;
 *   - the node rules of `meta`: `accepts` — the types allowed inside the node,
 *     instead of its type's; `locked` — with the original tree given (the
 *     stored one, `$original`), a locked node must keep its type, data, parent
 *     and children; it may go away only with an unlocked ancestor removed as
 *     a whole.
 *
 * The `meta` is the server's. With `$original` it is read from there — a
 * node's meta from the request counts only without it — so the panel cannot
 * widen the rules; a new block has none.
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
     * The meta of the nodes by ref, from the original tree, when given.
     *
     * @var array<string, array{node: array<array-key, mixed>, parent: string|null, path: string}>|null
     */
    private ?array $originalIndex = null;

    /**
     * @param  mixed  $nodes  The tree as it came from the request.
     * @param  list<array<string, mixed>>|null  $original  The stored tree, whose `meta` (accepts, locked) counts.
     * @return list<array{ref: int|string|null, path: string, field: string|null, message: string}>
     */
    public function errors(mixed $nodes, bool $data = true, ?array $original = null): array
    {
        $errors = [];
        if (! is_array($nodes) || ! array_is_list($nodes)) {
            return [self::error(null, '', null, __('Дерево блоков должно быть списком узлов'))];
        }

        $this->originalIndex = $original === null ? null : self::index($original);
        try {
            $seen = [];
            $this->walk($nodes, null, null, true, '', 1, $data, $seen, $errors);
            if ($this->originalIndex !== null) {
                $this->checkLocked($nodes, $this->originalIndex, $errors);
            }
        } finally {
            $this->originalIndex = null;
        }

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>|null  $original
     */
    public function passes(mixed $nodes, bool $data = true, ?array $original = null): bool
    {
        return $this->errors($nodes, $data, $original) === [];
    }

    /**
     * Throws a ValidationException when the tree is wrong: its messages are
     * keyed `{$key}.{path}` (`{$key}.{path}.data.{field}` for the data), and
     * the panel finds the node by its path.
     *
     * @param  list<array<string, mixed>>|null  $original  The stored tree, whose `meta` (accepts, locked) counts.
     *
     * @throws ValidationException
     */
    public function validate(mixed $nodes, string $key = 'nodes', bool $data = true, ?array $original = null): void
    {
        $errors = $this->errors($nodes, $data, $original);
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
     * @param  list<string>|null  $parentAccepts  The parent node's own `meta.accepts`; null — its type's.
     * @param  bool  $placed  Whether the parent is known, so the placement can be checked.
     * @param  array<array-key, true>  $seen
     * @param  list<array{ref: int|string|null, path: string, field: string|null, message: string}>  $errors
     */
    private function walk(array $nodes, ?BlockType $parent, ?array $parentAccepts, bool $placed, string $prefix, int $depth, bool $data, array &$seen, array &$errors): void
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
            } elseif ($parent !== null && ! ($parentAccepts === null ? $parent->allows($type->type()) : in_array($type->type(), $parentAccepts, true))) {
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
            $accepts = self::acceptsOf($this->metaOf($node, $ref));
            $this->walk($children, $type, $accepts, $type !== null, $path, $depth + 1, $data, $seen, $errors);
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

    /**
     * The node's meta: the original tree's when it is given (a node not
     * there — a new one — has none), else the node's own.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private function metaOf(array $node, int|string|null $ref): array
    {
        if ($this->originalIndex !== null) {
            $meta = $ref === null ? null : ($this->originalIndex[(string) $ref]['node']['meta'] ?? null);
        } else {
            $meta = $node['meta'] ?? null;
        }

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param  array<array-key, mixed>  $meta
     * @return list<string>|null
     */
    private static function acceptsOf(array $meta): ?array
    {
        $accepts = $meta['accepts'] ?? null;
        if (! is_array($accepts)) {
            return null;
        }

        return array_values(array_map(static fn (mixed $t): string => is_scalar($t) ? (string) $t : '', $accepts));
    }

    /**
     * Every node of a tree by its ref: the node, its parent's ref, its path.
     *
     * @param  array<array-key, mixed>  $nodes
     * @return array<string, array{node: array<array-key, mixed>, parent: string|null, path: string}>
     */
    private static function index(array $nodes, ?string $parent = null, string $prefix = ''): array
    {
        $index = [];
        foreach (array_values($nodes) as $i => $node) {
            if (! is_array($node)) {
                continue;
            }
            $ref = $node['ref'] ?? null;
            $path = $prefix === '' ? (string) $i : $prefix.'.children.'.$i;
            if (is_int($ref) || (is_string($ref) && $ref !== '')) {
                $index[(string) $ref] ??= ['node' => $node, 'parent' => $parent, 'path' => $path];
            }
            $children = $node['children'] ?? [];
            if (is_array($children)) {
                $index += self::index($children, is_int($ref) || is_string($ref) ? (string) $ref : null, $path);
            }
        }

        return $index;
    }

    /**
     * The locked nodes of the original tree against the tree given.
     *
     * @param  list<mixed>  $nodes
     * @param  array<string, array{node: array<array-key, mixed>, parent: string|null, path: string}>  $original
     * @param  list<array{ref: int|string|null, path: string, field: string|null, message: string}>  $errors
     */
    private function checkLocked(array $nodes, array $original, array &$errors): void
    {
        $now = self::index($nodes);
        foreach ($original as $key => $was) {
            $meta = $was['node']['meta'] ?? null;
            if (! is_array($meta) || ($meta['locked'] ?? false) !== true) {
                continue;
            }
            $ref = $was['node']['ref'];
            $label = $this->labelOf($was['node']['type'] ?? null);
            $is = $now[$key] ?? null;
            if ($is === null) {
                // Gone with its parent, removed as a whole, is not a removal of its own.
                if ($was['parent'] === null || isset($now[$was['parent']])) {
                    $errors[] = self::error($ref, $was['path'], null, __('Закреплённый блок «:type» нельзя удалять', ['type' => $label]));
                }

                continue;
            }
            $node = $is['node'];
            if ($is['parent'] !== $was['parent']) {
                $errors[] = self::error($ref, $is['path'], null, __('Закреплённый блок «:type» нельзя перемещать', ['type' => $label]));
            }
            if (($node['type'] ?? null) !== ($was['node']['type'] ?? null)
                || self::canonical($node['data'] ?? null) !== self::canonical($was['node']['data'] ?? null)) {
                $errors[] = self::error($ref, $is['path'], null, __('Закреплённый блок «:type» нельзя изменять', ['type' => $label]));
            }
            if (self::childRefs($node) !== self::childRefs($was['node'])) {
                $errors[] = self::error($ref, $is['path'], null, __('В закреплённом блоке «:type» нельзя менять вложенные блоки', ['type' => $label]));
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return list<string>
     */
    private static function childRefs(array $node): array
    {
        $children = $node['children'] ?? [];
        if (! is_array($children)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $c): string => is_array($c) && is_scalar($c['ref'] ?? null) ? (string) $c['ref'] : '',
            $children,
        ));
    }

    /** The data as JSON with the keys sorted; null and an empty object are the same. */
    private static function canonical(mixed $value): string
    {
        $sort = static function (mixed $v) use (&$sort): mixed {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($sort, $v);
        };

        return (string) json_encode($sort($value ?? []));
    }

    private function labelOf(mixed $type): string
    {
        if (! is_string($type)) {
            return '';
        }

        return isset($this->types[$type]) ? (string) $this->types[$type]->toArray()['label'] : $type;
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
