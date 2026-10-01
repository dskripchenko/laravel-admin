<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests\Support;

use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Infolist\Entry;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;
use Dskripchenko\LaravelAdmin\Widget\Widget;
use ReflectionClass;

/**
 * Every type string the backend can send to the SPA: field types, layout
 * types, widget types, infolist entry types and chart types.
 *
 * The list is written to resources/ts/__fixtures__/backend-types.json, which
 * the frontend parity test reads — so a type added on either side without
 * its counterpart fails a test instead of reaching a user as UnknownField.
 */
final class BackendTypes
{
    public const FIXTURE = __DIR__.'/../../resources/ts/__fixtures__/backend-types.json';

    /** @return array{fields: list<string>, layouts: list<string>, widgets: list<string>, entries: list<string>, charts: list<string>} */
    public static function collect(): array
    {
        $types = ['fields' => [], 'layouts' => [], 'widgets' => [], 'entries' => [], 'charts' => []];

        foreach (self::classesIn('Field') as $class) {
            if (is_subclass_of($class, Field::class)) {
                $types['fields'][] = self::instance($class)->fieldType();
            }
        }
        foreach (self::classesIn('Layout') as $class) {
            if (is_subclass_of($class, Layout::class)) {
                $types['layouts'][] = self::instance($class)->type();
            }
        }
        foreach (self::classesIn('Widget') as $class) {
            if (is_subclass_of($class, Widget::class)) {
                $types['widgets'][] = self::instance($class)->widgetType();
            }
        }
        foreach (self::classesIn('Infolist') as $class) {
            if (is_subclass_of($class, Entry::class)) {
                $types['entries'][] = self::instance($class)->entryType();
            }
        }
        $allowed = (new ReflectionClass(ChartWidget::class))->getConstant('ALLOWED_TYPES');
        $types['charts'] = is_array($allowed) ? array_values($allowed) : [];

        foreach ($types as $key => $list) {
            $list = array_values(array_unique($list));
            sort($list);
            $types[$key] = $list;
        }

        return $types;
    }

    public static function json(): string
    {
        return json_encode(self::collect(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    /** @return list<class-string> concrete classes directly under src/{$dir} */
    private static function classesIn(string $dir): array
    {
        $out = [];
        foreach (glob(__DIR__."/../../src/{$dir}/*.php") ?: [] as $file) {
            $class = 'Dskripchenko\\LaravelAdmin\\'.$dir.'\\'.basename($file, '.php');
            if (class_exists($class) && ! (new ReflectionClass($class))->isAbstract()) {
                $out[] = $class;
            }
        }

        return $out;
    }

    /** The type methods return constants, so a constructor-free instance is enough. */
    private static function instance(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
