<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings;

use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Settings\Storage\SettingsStorage;
use Illuminate\Support\Str;

/**
 * Singleton-ресурс настроек: одна группа из admin_settings, представленная
 * как форма Field'ов.
 *
 * Отличие от обычного Resource:
 *   - нет CRUD по записям (одна логическая «запись» = вся группа);
 *   - не требует Eloquent-модели;
 *   - read возвращает map ключ → значение, update сохраняет map обратно
 *     через SettingsStorage.
 *
 * Подключение в Admin осуществляется через `Admin::settings([...])`
 * (фаза P11.2), но базовый класс уже работает напрямую.
 */
abstract class SettingsResource
{
    /**
     * Slug группы (кеbab-case). Default: kebab(class basename без 'Settings'
     * суффикса).
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
     * Permission base. По default'у — `admin.settings.{slug}`.
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
     * Default-значения для отсутствующих ключей.
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
     * Получить текущие значения — мердж storage над defaults.
     *
     * @return array<string, mixed>
     */
    public function read(SettingsStorage $storage): array
    {
        return array_merge($this->defaults(), $storage->all(static::slug()));
    }

    /**
     * Сохранить значения с предварительной валидацией по rules.
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
     * Метаданные для манифеста (та же структура что у Resource::meta(),
     * но без table/columns/filters).
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
