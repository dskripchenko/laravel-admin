<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Resource\ResourceManifest;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;

/**
 * Builds the admin's JSON manifest for the SPA.
 *
 * The manifest holds the schemas of every resource and screen — nothing
 * secret — and the SPA uses it to:
 *   - resolve the /admin/resources/{slug} and /admin/screens/{slug} routes
 *   - build the form and the table out of FieldSchema/ColumnSchema/FilterSchema
 *   - check the UI permissions
 *
 * The manifest version is a sha256 of the serialized payload plus the admin
 * version, the locale and a hash of the user's permissions. That hash is
 * returned in the ETag and in the bootstrap (manifestVersion); the SPA
 * compares it and caches accordingly.
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
     * A memo of the assembled manifests, for the lifetime of the instance. It
     * removes the double build during bootstrap, where version() called a full
     * build() and /manifest then built it again.
     *
     * The instance lives EXACTLY one request: the binding is `scoped()`, not
     * `singleton()`. It used to say here that "a singleton is one HTTP request
     * under FPM" — which is wrong under Octane, where the worker outlives the
     * request and the memo becomes a cross-request cache keyed by something
     * that may not describe everything the content depends on.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $built = [];

    /**
     * Clears the memo, for tests that mutate the registries between builds.
     */
    public function flush(): void
    {
        $this->built = [];
    }

    /**
     * Builds the manifest for the current user and locale.
     *
     * Resources and screens are listed with their permissions and the SPA
     * hides what the user may not open; dashboards are filtered here, since
     * they carry computed data.
     *
     * @return array<string, mixed>
     */
    public function build(string $locale = 'ru', ?string $panel = null): array
    {
        // Panels: null means the default panel's manifest, kept for backward compatibility.
        $panel ??= 'admin';
        $memoKey = $locale.'|'.$panel;
        if (isset($this->built[$memoKey])) {
            return $this->built[$memoKey];
        }

        $resourcesPayload = [];
        foreach ($this->resources->all($panel) as $slug => $class) {
            $resource = $this->resources->resolve($slug);
            if ($resource === null) {
                continue;
            }
            $resourcesPayload[] = ResourceManifest::describe($resource);
        }

        // Only custom screens go into `screens`. GeneratedScreen (inside a
        // resource) and DashboardScreen have their own controllers and their
        // own sections of the manifest: `resources` and `dashboards`.
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
                'name' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($screen->name()),
                'description' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($screen->description()),
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

        // Dashboards: every DashboardScreen the user may open is exported as
        // { slug, label, description, permission, periods, period, widgets[] }
        // for the frontend's DashboardPage, keyed by slug in
        // manifest.dashboards. A dashboard the user has no permission for is
        // left out altogether, and so is every widget they may not see — its
        // data() is never called. The widgets are the output of
        // Widget::toArray(), resolved by the frontend through its registry by
        // the `type` field. See DashboardScreen for the rules.
        $dashboardsPayload = [];
        foreach ($this->screens->all($panel) as $slug => $class) {
            if (! is_subclass_of($class, \Dskripchenko\LaravelAdmin\Widget\DashboardScreen::class)) {
                continue;
            }
            // Resolved through the container, so that a DashboardScreen with a
            // typed constructor gets its dependencies.
            $screen = app($class);
            if (! $screen instanceof \Dskripchenko\LaravelAdmin\Widget\DashboardScreen || ! $screen->canAccess()) {
                continue;
            }
            $dashboardsPayload[] = $screen->toManifest($slug);
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

        return $this->built[$memoKey] = [
            'version' => $this->buildVersion($payload),
            ...$payload,
        ];
    }

    /**
     * The manifest hash: deterministic, built from the content and the admin version.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildVersion(array $payload): string
    {
        $signature = (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return substr(hash('sha256', $this->admin->version().'|'.$signature), 0, 32);
    }

    /**
     * The manifest's current version, without a full build — for a cheap ETag comparison.
     */
    public function version(string $locale = 'ru', ?string $panel = null): string
    {
        return $this->build($locale, $panel)['version'];
    }
}
