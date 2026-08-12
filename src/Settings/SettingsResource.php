<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings;

use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Settings\Storage\SettingsStorage;
use Illuminate\Support\Str;

/**
 * A singleton settings resource: one group of admin_settings, presented as a
 * form of fields.
 *
 * How it differs from an ordinary resource:
 *   - there is no CRUD over records; the one logical "record" is the whole
 *     group;
 *   - it needs no Eloquent model;
 *   - read returns a key → value map, and update saves that map back through
 *     SettingsStorage.
 *
 * It is registered with `Admin::settings([...])`, though the base class works
 * directly too.
 */
abstract class SettingsResource
{
    /**
     * The group's slug, in kebab case; by default the class basename without
     * the 'Settings' suffix.
     */
    public static function slug(): string
    {
        $base = class_basename(static::class);
        if (str_ends_with($base, 'Settings')) {
            $base = substr($base, 0, -strlen('Settings'));
        }
        if (str_ends_with($base, 'Resource')) {
            $base = substr($base, 0, -strlen('Resource'));
        }

        return Str::kebab($base);
    }

    /**
     * The permission base; `admin.settings.{slug}` by default.
     */
    public static function permission(): string
    {
        return 'admin.settings.'.static::slug();
    }

    public static function label(): string
    {
        $base = class_basename(static::class);
        if (str_ends_with($base, 'Settings')) {
            $base = substr($base, 0, -strlen('Settings'));
        }
        if (str_ends_with($base, 'Resource')) {
            $base = substr($base, 0, -strlen('Resource'));
        }

        return Str::headline($base);
    }

    /**
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * The default values of the keys that are not stored.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->fields() as $field) {
            $defaults[$field->name()] = $field->getDefaultValue();
        }

        return $defaults;
    }

    /**
     * Returns the current values: the storage merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public function read(SettingsStorage $storage): array
    {
        return array_merge($this->defaults(), $storage->all(static::slug()));
    }

    /**
     * Saves the values, validating them against the rules first.
     *
     * @param  array<string, mixed>  $values
     */
    public function write(SettingsStorage $storage, array $values): void
    {
        $rules = $this->validationRules();
        if ($rules !== []) {
            validator($values, $rules)->validate();
        }
        $storage->save(static::slug(), $values);
    }

    /**
     * @return array<string, list<string>>
     */
    public function validationRules(): array
    {
        return ValidationRulesExporter::export($this->fields(), 'update');
    }

    /**
     * The metadata for the manifest — the same shape as Resource::meta() but
     * without the table, the columns and the filters.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $base = static::permission();

        return [
            'kind' => 'settings',
            'slug' => static::slug(),
            'label' => static::label(),
            'permissions' => [
                'view' => $base.'.view',
                'update' => $base.'.update',
            ],
            'fields' => array_map(
                static fn (Field $f): array => $f->toArray(),
                $this->fields(),
            ),
        ];
    }
}
