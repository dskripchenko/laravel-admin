<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\OpenApi;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\DropDown;
use Dskripchenko\LaravelAdmin\Filter\Filter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Settings\SettingsResource;

/**
 * The request schemas of the operations whose input depends on the resource.
 *
 * One ResourceController serves every resource, so its docblocks cannot list
 * the fields of any of them. laravel-api asks `@input [operationSchema]` once
 * per route, with the controller key — the resource slug — in the context;
 * this class answers for one resource and one action.
 */
final class ResourceOperationSchema
{
    /**
     * The input schema of `$action` on `$resource`, or [] when the action's
     * input does not depend on the resource.
     *
     * @return array<string, mixed>
     */
    public static function forAction(Resource $resource, string $action): array
    {
        return match ($action) {
            'create' => self::form($resource, 'create'),
            'update' => self::form($resource, 'update'),
            'search' => self::listing($resource, withOrder: true, withGroupBy: true),
            'summary', 'tree' => self::listing($resource),
            'export' => self::export($resource),
            'action' => self::bulkAction($resource),
            'inlineUpdate' => self::inlineUpdate($resource),
            'reorder' => self::reorder($resource),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function reorder(Resource $resource): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'description' => 'New positions, written to the `'.$resource->reorderColumn().'` column',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'description' => 'The primary key',
                                'oneOf' => [['type' => 'integer'], ['type' => 'string']],
                            ],
                            'position' => ['type' => 'integer', 'minimum' => 0],
                        ],
                        'required' => ['id', 'position'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }

    /**
     * The `values` of a settings group.
     *
     * @return array<string, mixed>
     */
    public static function forSettings(SettingsResource $settings): array
    {
        $values = RulesSchema::object($settings->validationRules(), $settings->fields());
        $values['description'] = 'The values to save, by key';

        return [
            'type' => 'object',
            'properties' => ['values' => $values],
            'required' => ['values'],
        ];
    }

    /**
     * The record's fields, as validationRules() checks them in that context.
     *
     * @return array<string, mixed>
     */
    private static function form(Resource $resource, string $context): array
    {
        $fields = array_values(array_filter(
            $resource->fields(),
            static fn ($field): bool => $field->appliesTo($context),
        ));

        return RulesSchema::object($resource->validationRules($context), $fields);
    }

    /**
     * @return array<string, mixed>
     */
    private static function listing(Resource $resource, bool $withOrder = false, bool $withGroupBy = false): array
    {
        $properties = [
            'filters' => self::filters($resource),
            'q' => [
                'type' => 'string',
                'description' => $resource->searchableFields() === []
                    ? 'Free-text search; this resource declares no searchable columns, so it is ignored'
                    : 'Free-text search over: '.implode(', ', $resource->searchableFields()),
            ],
        ];

        if ($withOrder) {
            $column = ['type' => 'string'];
            $sortable = self::columnNames($resource, 'sortable');
            if ($sortable !== []) {
                $column['enum'] = $sortable;
            }
            $properties['order'] = [
                'type' => 'array',
                'description' => 'Sort order; the resource default applies when empty',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'column' => $column,
                        'direction' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                    ],
                    'required' => ['column'],
                ],
            ];
        }

        if ($withGroupBy) {
            $properties['group_by'] = [
                'type' => 'string',
                'description' => 'A column to count the matching records by; the counts come back in meta.groups',
            ];
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * @return array<string, mixed>
     */
    private static function export(Resource $resource): array
    {
        $schema = self::listing($resource);

        $columns = ['type' => 'string'];
        $names = self::columnNames($resource);
        if ($names !== []) {
            $columns['enum'] = $names;
        }

        $formats = [];
        try {
            /** @var \Dskripchenko\LaravelAdmin\Export\ExporterRegistry $registry */
            $registry = app(\Dskripchenko\LaravelAdmin\Export\ExporterRegistry::class);
            $formats = $registry->formats();
        } catch (\Throwable) {
            // No registry bound: the format stays a plain string.
        }

        $format = ['type' => 'string', 'default' => 'csv', 'description' => 'One of the registered export formats'];
        if ($formats !== []) {
            $format['enum'] = $formats;
        }

        $schema['properties']['format'] = $format;
        $schema['properties']['columns'] = [
            'type' => 'array',
            'description' => 'The columns to export; all of them when empty',
            'items' => $columns,
        ];

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function bulkAction(Resource $resource): array
    {
        $key = ['type' => 'string', 'description' => 'The action, as the resource declares it'];
        $names = self::actionNames($resource->actions());
        if ($names !== []) {
            $key['enum'] = $names;
        }

        return [
            'type' => 'object',
            'properties' => [
                'ids' => [
                    'type' => 'array',
                    'description' => 'The primary keys of the records; at least one',
                    'minItems' => 1,
                    'items' => ['oneOf' => [['type' => 'integer'], ['type' => 'string']]],
                ],
                'key' => $key,
            ],
            'required' => ['ids', 'key'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function inlineUpdate(Resource $resource): array
    {
        $names = self::columnNames($resource, 'editable');
        if ($names === []) {
            return [];
        }

        return [
            'type' => 'object',
            'properties' => [
                'column' => [
                    'type' => 'string',
                    'enum' => $names,
                    'description' => 'A column declared editable in the table',
                ],
            ],
            'required' => ['column'],
        ];
    }

    /**
     * Filters arrive keyed by field, or as a list of {column, value, operator}.
     *
     * @return array<string, mixed>
     */
    private static function filters(Resource $resource): array
    {
        $byField = [];
        $names = [];
        foreach ($resource->resolvedFilters() as $filter) {
            /** @var Filter $filter */
            $meta = $filter->toArray();
            $names[] = $filter->field();
            $byField[$filter->field()] = [
                'description' => trim(((string) ($meta['label'] ?? '')).' ('.$filter->type().' filter)'),
            ];
        }

        $column = ['type' => 'string'];
        if ($names !== []) {
            $column['enum'] = $names;
        }

        $map = ['type' => 'object', 'description' => 'Filter values keyed by field'];
        if ($byField !== []) {
            $map['properties'] = $byField;
        }

        return [
            'description' => $names === []
                ? 'This resource declares no filters'
                : 'Filter values, keyed by field or as a list of {column, value}',
            'oneOf' => [
                $map,
                [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'column' => $column,
                            'value' => (object) [],
                            'operator' => ['type' => 'string'],
                        ],
                        'required' => ['column'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function columnNames(Resource $resource, ?string $flag = null): array
    {
        $names = [];
        foreach ($resource->columns() as $column) {
            if ($flag !== null) {
                $value = $column->toArray()[$flag] ?? null;
                if ($value === null || $value === false) {
                    continue;
                }
            }
            $names[] = $column->name();
        }

        return array_values(array_unique($names));
    }

    /**
     * The action names a bulk request may carry, DropDown items included.
     *
     * @param  array<array-key, mixed>  $actions
     * @return list<string>
     */
    private static function actionNames(array $actions): array
    {
        $names = [];
        foreach ($actions as $action) {
            if ($action instanceof DropDown) {
                $names = array_merge($names, self::actionNames($action->getItems()));

                continue;
            }
            // Only an action bound to a resource method can be dispatched.
            if ($action instanceof Action
                && $action->name() !== ''
                && is_string($action->toArray()['attributes']['method'] ?? null)
            ) {
                $names[] = $action->name();
            }
        }

        return array_values(array_unique($names));
    }
}
