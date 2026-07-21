<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Panel;

use Dskripchenko\LaravelApi\Facades\ApiRequest;

/**
 * Реестр панелей + резолв «текущей» панели запроса.
 *
 * Текущая панель определяется (по убыванию приоритета):
 *   1. явный setCurrent() (тесты, консольные сценарии);
 *   2. атрибут запроса `admin.panel` (ставится shell-роутом панели);
 *   3. API-версия laravel-api текущего запроса (версия == id панели);
 *   4. дефолтная панель `admin`.
 */
final class PanelRegistry
{
    /** @var array<string, Panel>|null */
    private ?array $panels = null;

    private ?string $current = null;

    /**
     * @return array<string, Panel>
     */
    public function all(): array
    {
        if ($this->panels === null) {
            $panels = ['admin' => Panel::default()];
            /** @var array<array-key, mixed> $configured */
            $configured = (array) config('admin.panels', []);
            foreach ($configured as $id => $config) {
                if (! is_string($id) || $id === 'admin' || ! is_array($config)) {
                    continue;
                }
                $panels[$id] = Panel::fromConfig($id, $config);
            }
            $this->panels = $panels;
        }

        return $this->panels;
    }

    public function get(string $id): ?Panel
    {
        return $this->all()[$id] ?? null;
    }

    public function default(): Panel
    {
        return $this->all()['admin'];
    }

    public function has(string $id): bool
    {
        return isset($this->all()[$id]);
    }

    public function setCurrent(?string $id): void
    {
        $this->current = $id;
    }

    public function current(): Panel
    {
        if ($this->current !== null) {
            return $this->get($this->current) ?? $this->default();
        }

        // В консоли request — заглушка из глобалов: атрибуты/route/api-version
        // пусты, резолв честно падает в дефолт. Отдельной console-ветки не надо
        // (а в HTTP-тестах runningInConsole() === true — проверка ломала бы их).
        if (app()->bound('request')) {
            $request = request();

            $attr = $request->attributes->get('admin.panel');
            if (is_string($attr) && $this->has($attr)) {
                return $this->all()[$attr];
            }

            $route = $request->route();
            if ($route !== null) {
                $param = $route->parameter('adminPanel');
                if (is_string($param) && $this->has($param)) {
                    return $this->all()[$param];
                }
            }

            // Фасад laravel-api аннотирует string, фактически бывает null —
            // нормализуем к '' (пустая строка панелью не бывает).
            $version = (string) ApiRequest::getApiVersion();
            if ($version !== '' && $this->has($version)) {
                return $this->all()[$version];
            }
        }

        return $this->default();
    }

    /**
     * Сбрасывает кеш панелей (config изменился в тестах).
     */
    public function flush(): void
    {
        $this->panels = null;
        $this->current = null;
    }
}
