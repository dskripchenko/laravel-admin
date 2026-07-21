<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Layout\Dashboard;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Абстрактный Dashboard-экран. Конкретный подкласс декларирует список widgets
 * через `widgets()`.
 *
 * compile()/layout() автоматически:
 *   1. Применяет per-user customization из admin_dashboard_layouts (если есть);
 *   2. Скрывает widgets без permission'а;
 *   3. Оборачивает в Dashboard layout с key=$this->key().
 *
 * @method string|null name()
 */
abstract class DashboardScreen extends Screen
{
    /**
     * Текущий period (7d/30d/90d/all). Передаётся через withPeriod()
     * на /dashboard/widgets endpoint'е. По умолчанию 30d.
     */
    protected string $period = '30d';

    /**
     * Уникальный ключ — используется как `dashboard_key` в DashboardLayout.
     */
    public function key(): string
    {
        return static::slug();
    }

    public function withPeriod(string $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function period(): string
    {
        return $this->period;
    }

    /**
     * Конвертирует period в количество дней. Для 'all' возвращает большое
     * число (10 лет), чтобы всё попало в окно.
     */
    public function periodDays(): int
    {
        return match ($this->period) {
            '7d' => 7,
            '90d' => 90,
            'all' => 365 * 10,
            default => 30,
        };
    }

    /**
     * @return list<Widget>
     */
    abstract public function widgets(): array;

    /**
     * @return Repository|array<string, mixed>
     */
    public function query(mixed ...$params): Repository|array
    {
        return [];
    }

    /**
     * @return list<Layout>
     */
    public function layout(): array
    {
        $widgets = $this->effectiveWidgets();

        return [
            Dashboard::make($widgets)->key($this->key()),
        ];
    }

    /**
     * Применить per-user layout (если найден) и фильтровать по permission.
     *
     * @return list<Widget>
     */
    private function effectiveWidgets(): array
    {
        $declared = $this->widgets();
        $declaredBySlug = [];
        foreach ($declared as $w) {
            $declaredBySlug[$w::slug()] = $w;
        }

        $persisted = $this->loadPersistedLayout();

        // Сборка финального списка.
        $result = [];
        if ($persisted !== null) {
            usort($persisted, static fn (array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
            foreach ($persisted as $item) {
                $slug = (string) ($item['slug'] ?? '');
                if (! isset($declaredBySlug[$slug])) {
                    continue; // Widget удалён из кода с момента сохранения layout'а.
                }
                $widget = $declaredBySlug[$slug];
                unset($declaredBySlug[$slug]);

                if (($item['hidden'] ?? false) === true) {
                    continue;
                }
                if (isset($item['size']) && is_int($item['size'])) {
                    $widget->size($item['size']);
                }
                $result[] = $widget;
            }
            // Новые widgets, ещё не присутствующие в persisted layout — в конец.
            foreach ($declaredBySlug as $widget) {
                $result[] = $widget;
            }
        } else {
            $result = $declared;
        }

        return array_values(array_filter($result, fn (Widget $w): bool => $this->isWidgetVisible($w)));
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function loadPersistedLayout(): ?array
    {
        $user = $this->currentUser();
        if ($user === null) {
            return null;
        }

        $layout = DashboardLayout::query()
            ->where('dashboard_key', $this->key())
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->first();

        if ($layout === null) {
            return null;
        }

        return $layout->widgets;
    }

    private function currentUser(): ?Model
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user : null;
    }

    private function isWidgetVisible(Widget $widget): bool
    {
        if (! $widget->isVisible()) {
            return false;
        }

        $permission = $widget->getPermission();
        if ($permission === null) {
            return true;
        }

        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }
        if (! method_exists($user, 'hasAccess')) {
            return true;
        }

        $permissions = is_array($permission) ? $permission : [$permission];
        foreach ($permissions as $p) {
            if (! $user->hasAccess($p)) {
                return false;
            }
        }

        return true;
    }
}
