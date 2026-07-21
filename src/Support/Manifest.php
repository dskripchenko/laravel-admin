<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Resource\ResourceManifest;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;

/**
 * Сборщик JSON-манифеста admin для SPA.
 *
 * Манифест содержит схемы всех Resource'ов / Screen'ов (без секретных
 * данных) и используется SPA для:
 *   - резолвинга роутов /admin/resources/{slug} и /admin/screens/{slug}
 *   - построения формы/таблицы из FieldSchema/ColumnSchema/FilterSchema
 *   - проверки UI-permissions
 *
 * Manifest version — sha256 от сериализованного payload + admin version +
 * locale + permissions hash юзера. Этот хэш отдаётся в ETag и в bootstrap'е
 * (manifestVersion), SPA сравнивает и кэширует.
 */
final class Manifest
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ScreenRegistry $screens,
        private readonly Admin $admin,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * Собрать manifest для текущего пользователя и локали.
     *
     * На P1 фильтрация по permissions ещё не делается — все resource'ы видны.
     * На P2 будет приниматься AdminUser и фильтроваться.
     *
     * @return array<string, mixed>
     */
    public function build(string $locale = 'ru', ?string $panel = null): array
    {
        // v1.8 Panels: null — манифест дефолтной панели (BC).
        $panel ??= 'admin';

        $resourcesPayload = [];
        foreach ($this->resources->all($panel) as $slug => $class) {
            $resource = $this->resources->resolve($slug);
            if ($resource === null) {
                continue;
            }
            $resourcesPayload[] = ResourceManifest::describe($resource);
        }

        // В `screens` попадают только custom Screen-ы. GeneratedScreen
        // (внутри Resource) и DashboardScreen имеют отдельные controllers
        // и собственные секции в манифесте (`resources` / `dashboards`).
        $screensPayload = [];
        foreach ($this->screens->all($panel) as $slug => $class) {
            if (is_subclass_of($class, \Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedScreen::class)) {
                continue;
            }
            if (is_subclass_of($class, \Dskripchenko\LaravelAdmin\Widget\DashboardScreen::class)) {
                continue;
            }
            $screen = $this->admin->resolveScreen($slug);
            if ($screen === null) {
                continue;
            }
            $screensPayload[] = [
                'slug' => $slug,
                'name' => $screen->name(),
                'description' => $screen->description(),
                'permission' => $screen->permission(),
            ];
        }

        $settingsPayload = [];
        foreach ($this->settings->all($panel) as $slug => $class) {
            $settings = $this->settings->resolve($slug);
            if ($settings === null) {
                continue;
            }
            $settingsPayload[] = $settings->meta();
        }

        // Dashboards: каждый DashboardScreen экспортируется как
        // { slug, label, description, widgets[] } для frontend
        // DashboardPage (slug в manifest.dashboards). widgets — выход
        // Widget::toArray() — `{kind, slug, type, title, size, ...}`,
        // фронт-renderer резолвит через registry по полю `type`.
        $dashboardsPayload = [];
        foreach ($this->screens->all($panel) as $slug => $class) {
            // ScreenRegistry хранит class-strings; resolve через container,
            // чтобы DI инжектил зависимости (если у конкретного DashboardScreen
            // есть конструктор с типизированными аргументами).
            if (! is_subclass_of($class, \Dskripchenko\LaravelAdmin\Widget\DashboardScreen::class)) {
                continue;
            }
            $screen = app($class);
            if (! $screen instanceof \Dskripchenko\LaravelAdmin\Widget\DashboardScreen) {
                continue;
            }
            $widgets = [];
            foreach ($screen->widgets() as $widget) {
                if (! $widget->isVisible()) {
                    continue;
                }
                $widgets[] = $widget->toArray();
            }
            $dashboardsPayload[] = [
                'slug' => $slug,
                'label' => $screen->name() ?? $slug,
                'description' => $screen->description(),
                'widgets' => $widgets,
            ];
        }

        $payload = [
            'locale' => $locale,
            'panel' => $panel,
            'resources' => $resourcesPayload,
            'screens' => $screensPayload,
            'settings' => $settingsPayload,
            'dashboards' => $dashboardsPayload,
            'plugins' => $this->admin->getPlugins(),
            'permissions' => [],
        ];

        return [
            'version' => $this->buildVersion($payload),
            ...$payload,
        ];
    }

    /**
     * Хэш манифеста — детерминированный, основан на содержимом + версии admin.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildVersion(array $payload): string
    {
        $signature = (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return substr(hash('sha256', $this->admin->version().'|'.$signature), 0, 32);
    }

    /**
     * Текущая версия манифеста (без полной сборки) — для cheap ETag-сравнения.
     */
    public function version(string $locale = 'ru', ?string $panel = null): string
    {
        return $this->build($locale, $panel)['version'];
    }
}
