<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use InvalidArgumentException;

/**
 * The server side of the @dskripchenko/flow model: reading it from JSON or an
 * array, checking its shape and preparing it for the SPA.
 *
 * The checks follow the package's JSON Schema (graph.schema.json) and the
 * errors of its `validateGraph`: an object with a `nodes` list; nodes, edges
 * and groups with non-empty string ids, unique across all three; edges between
 * existing nodes; groups that exist; inline sub-processes checked the same
 * way. The warnings of `validateGraph` (unreachable steps, dead ends) are the
 * editor's business and do not fail here.
 *
 *     FlowGraph::errors($value);            // [] or ['/edges/2/to: …', …]
 *     FlowGraph::parse($jsonOrArray);       // the array, or InvalidArgumentException
 */
final class FlowGraph
{
    /** The node limit of the package's layout (MAX_NODES). */
    public const MAX_NODES = 500;

    /** The edge limit of the package's layout (MAX_EDGES). */
    public const MAX_EDGES = 2000;

    /**
     * The model as an array: decodes a JSON string, checks the shape and
     * throws on the first problem.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function parse(mixed $value): array
    {
        $decoded = self::decode($value);
        $errors = self::errors($decoded);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid flow model: '.$errors[0]);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * A JSON string decoded to an array; anything else is returned as is.
     */
    public static function decode(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            $decoded = json_decode($trimmed, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }
        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        return $value;
    }

    /**
     * Every problem with the model's shape, each prefixed with a JSON pointer
     * to the offending value; an empty list for a valid model.
     *
     * @param  list<string>|null  $types  When given, the node types allowed.
     * @return list<string>
     */
    public static function errors(mixed $graph, ?array $types = null): array
    {
        $errors = [];
        self::check($graph, '', $types, $errors);

        return $errors;
    }

    /**
     * The model ready for JSON: empty `data` and `meta` stay objects (PHP
     * turns `{}` into `[]`), recursively through inline sub-processes.
     *
     * @param  array<string, mixed>  $graph
     * @param  (callable(string): string)|null  $translate  Applied to the captions: labels, descriptions, the title.
     * @return array<string, mixed>
     */
    public static function forJson(array $graph, ?callable $translate = null): array
    {
        if (array_key_exists('meta', $graph) && is_array($graph['meta'])) {
            $meta = $graph['meta'];
            if ($translate !== null && isset($meta['title']) && is_string($meta['title'])) {
                $meta['title'] = $translate($meta['title']);
            }
            $graph['meta'] = $meta === [] ? new \stdClass : $meta;
        }
        foreach (['nodes', 'edges', 'groups'] as $list) {
            if (! isset($graph[$list]) || ! is_array($graph[$list])) {
                continue;
            }
            foreach ($graph[$list] as $i => $element) {
                if (! is_array($element)) {
                    continue;
                }
                if ($translate !== null) {
                    foreach (['label', 'description'] as $key) {
                        if (isset($element[$key]) && is_string($element[$key])) {
                            $element[$key] = $translate($element[$key]);
                        }
                    }
                }
                if (array_key_exists('data', $element) && $element['data'] === []) {
                    $element['data'] = new \stdClass;
                }
                if (isset($element['subgraph']) && is_array($element['subgraph'])) {
                    /** @var array<string, mixed> $sub */
                    $sub = $element['subgraph'];
                    $element['subgraph'] = self::forJson($sub, $translate);
                }
                $graph[$list][$i] = $element;
            }
        }

        return $graph;
    }

    /**
     * @param  list<string>|null  $types
     * @param  list<string>  $errors
     */
    private static function check(mixed $graph, string $at, ?array $types, array &$errors): void
    {
        if (! is_array($graph) || array_is_list($graph) && $graph !== []) {
            $errors[] = self::at($at, __('ожидается объект модели процесса'));

            return;
        }
        if (array_key_exists('version', $graph) && $graph['version'] !== 1) {
            $errors[] = self::at($at.'/version', __('неподдерживаемая версия модели'));
        }
        if (! isset($graph['nodes']) || ! is_array($graph['nodes']) || ! array_is_list($graph['nodes'])) {
            $errors[] = self::at($at.'/nodes', __('ожидается список шагов'));

            return;
        }
        foreach (['edges', 'groups'] as $list) {
            if (array_key_exists($list, $graph) && (! is_array($graph[$list]) || ! array_is_list($graph[$list]))) {
                $errors[] = self::at($at.'/'.$list, __('ожидается список'));

                return;
            }
        }
        if (array_key_exists('meta', $graph) && ! is_array($graph['meta'])) {
            $errors[] = self::at($at.'/meta', __('ожидается объект'));
        }

        /** @var list<mixed> $nodes */
        $nodes = $graph['nodes'];
        /** @var list<mixed> $edges */
        $edges = $graph['edges'] ?? [];
        /** @var list<mixed> $groups */
        $groups = $graph['groups'] ?? [];

        if (count($nodes) > self::MAX_NODES || count($edges) > self::MAX_EDGES) {
            $errors[] = self::at($at, __('слишком большая модель: не более :nodes шагов и :edges переходов', [
                'nodes' => self::MAX_NODES, 'edges' => self::MAX_EDGES,
            ]));

            return;
        }

        /** @var array<string, true> $seen */
        $seen = [];
        $nodeIds = [];
        $groupIds = [];
        $claim = static function (mixed $id, string $path) use (&$seen, &$errors): ?string {
            if (! is_string($id) || $id === '') {
                $errors[] = self::at($path, __('ожидается непустой идентификатор'));

                return null;
            }
            if (isset($seen[$id])) {
                $errors[] = self::at($path, __('повторяющийся идентификатор «:id»', ['id' => $id]));

                return null;
            }
            $seen[$id] = true;

            return $id;
        };

        foreach ($groups as $i => $group) {
            $path = "{$at}/groups/{$i}";
            if (! is_array($group)) {
                $errors[] = self::at($path, __('ожидается объект'));

                continue;
            }
            $id = $claim($group['id'] ?? null, $path.'/id');
            if ($id !== null) {
                $groupIds[$id] = true;
            }
        }

        foreach ($nodes as $i => $node) {
            $path = "{$at}/nodes/{$i}";
            if (! is_array($node) || array_is_list($node)) {
                $errors[] = self::at($path, __('ожидается объект'));

                continue;
            }
            $id = $claim($node['id'] ?? null, $path.'/id');
            if ($id !== null) {
                $nodeIds[$id] = true;
            }
            if (array_key_exists('type', $node)) {
                if (! is_string($node['type']) || $node['type'] === '') {
                    $errors[] = self::at($path.'/type', __('ожидается строка'));
                } elseif ($types !== null && ! in_array($node['type'], $types, true)) {
                    $errors[] = self::at($path.'/type', __('тип «:type» не разрешён', ['type' => $node['type']]));
                }
            }
            self::checkStrings($node, ['label', 'description', 'group'], $path, $errors);
            if (array_key_exists('enabled', $node) && ! is_bool($node['enabled'])) {
                $errors[] = self::at($path.'/enabled', __('ожидается логическое значение'));
            }
            if (array_key_exists('position', $node)) {
                $p = $node['position'];
                if (! is_array($p) || ! is_numeric($p['x'] ?? null) || ! is_numeric($p['y'] ?? null)) {
                    $errors[] = self::at($path.'/position', __('ожидаются координаты x и y'));
                }
            }
            if (array_key_exists('data', $node) && ! is_array($node['data'])) {
                $errors[] = self::at($path.'/data', __('ожидается объект'));
            }
            if (isset($node['group']) && is_string($node['group']) && ! isset($groupIds[$node['group']])) {
                $errors[] = self::at($path.'/group', __('нет группы «:id»', ['id' => $node['group']]));
            }
            if (array_key_exists('subgraph', $node)) {
                $sub = $node['subgraph'];
                if (is_array($sub)) {
                    self::check($sub, $path.'/subgraph', $types, $errors);
                } elseif (! is_string($sub) || $sub === '') {
                    $errors[] = self::at($path.'/subgraph', __('ожидается ссылка или вложенная модель'));
                }
            }
        }

        foreach ($edges as $i => $edge) {
            $path = "{$at}/edges/{$i}";
            if (! is_array($edge) || array_is_list($edge)) {
                $errors[] = self::at($path, __('ожидается объект'));

                continue;
            }
            $claim($edge['id'] ?? null, $path.'/id');
            foreach (['from', 'to'] as $end) {
                $ref = $edge[$end] ?? null;
                if (! is_string($ref) || $ref === '') {
                    $errors[] = self::at("{$path}/{$end}", __('ожидается непустой идентификатор'));
                } elseif (! isset($nodeIds[$ref])) {
                    $errors[] = self::at("{$path}/{$end}", __('нет шага «:id»', ['id' => $ref]));
                }
            }
            self::checkStrings($edge, ['label', 'condition', 'fromPort', 'toPort'], $path, $errors);
            if (array_key_exists('enabled', $edge) && ! is_bool($edge['enabled'])) {
                $errors[] = self::at($path.'/enabled', __('ожидается логическое значение'));
            }
            if (array_key_exists('kind', $edge) && ! in_array($edge['kind'], ['transition', 'association'], true)) {
                $errors[] = self::at($path.'/kind', __('ожидается transition или association'));
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $element
     * @param  list<string>  $keys
     * @param  list<string>  $errors
     */
    private static function checkStrings(array $element, array $keys, string $path, array &$errors): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $element) && ! is_string($element[$key])) {
                $errors[] = self::at("{$path}/{$key}", __('ожидается строка'));
            }
        }
    }

    private static function at(string $pointer, mixed $message): string
    {
        return ($pointer === '' ? '/' : $pointer).': '.(is_string($message) ? $message : '');
    }
}
