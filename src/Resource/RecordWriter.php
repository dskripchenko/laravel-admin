<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Field\Wysiwyg;
use Dskripchenko\LaravelAdmin\Theme\TranslatableFieldBridge;
use Dskripchenko\LaravelAdmin\Uploads\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;

/**
 * The one way a resource's record is created from form data: the resource's
 * `{slug}/create` action and the import both go through it, so an imported row
 * is written exactly as a submitted form would be — the WYSIWYG fields
 * sanitized, the translatable fields split off, the model filled by the
 * resource's own `fillModel()`.
 *
 * The data must already be validated against `validationRules('create')`:
 * only the keys of the resource's create form may reach it.
 */
final class RecordWriter
{
    /**
     * Creates and saves a record. A QueryException is left to the caller.
     *
     * @param  array<string, mixed>  $data  The validated form data.
     */
    public static function create(Resource $resource, array $data): Model
    {
        $data = self::sanitizeWysiwyg($resource, $data);
        $translations = TranslatableFieldBridge::extract($resource->fields(), $data);

        $modelClass = $resource::$model;
        /** @var Model $record */
        $record = new $modelClass;
        $resource->fillModel($record, $data);
        $record->save();
        TranslatableFieldBridge::saveAll($record, $translations);

        return $record;
    }

    /**
     * The names of the fields the resource's create form holds — the only
     * keys a create, or an import, may write.
     *
     * @return list<string>
     */
    public static function createFieldNames(Resource $resource): array
    {
        $names = [];
        foreach ($resource->fields() as $field) {
            if ($field->appliesTo('create')) {
                $names[] = $field->name();
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Runs the HTML of every Wysiwyg field with shouldSanitize() = true
     * through HtmlSanitizer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeWysiwyg(Resource $resource, array $data): array
    {
        foreach ($resource->fields() as $field) {
            if (! $field instanceof Wysiwyg) {
                continue;
            }
            if (! $field->shouldSanitize()) {
                continue;
            }
            $name = $field->name();
            if (! array_key_exists($name, $data) || ! is_string($data[$name])) {
                continue;
            }
            // The whitelist follows the field's engine (Quill and TinyMCE keep
            // their own markup).
            $sanitizer = new HtmlSanitizer(
                $field->sanitizerWhitelist(),
                $field->sanitizerConstrains(),
            );
            $data[$name] = $sanitizer->sanitize($data[$name]);
        }

        return $data;
    }
}
