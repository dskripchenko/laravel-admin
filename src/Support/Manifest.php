<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Resource\ResourceManifest;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

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
 *
 * One broken section never takes the panel down: a resource, screen, settings
 * page or dashboard that throws while it is described is logged and left out,
 * and with app.debug on the manifest lists it under `diagnostics`, which the
 * SPA shows to the administrator as a warning.
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
     * The sections left out of the build in progress.
     *
     * @var list<array{kind: string, slug: string, class: string, error: string, message: string}>
     */
    private array $skipped = [];

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

        $this->skipped = [];

        $resourcesPayload = [];
        foreach ($this->resources->all($panel) as $slug => $class) {
            $described = $this->guard('resource', $slug, $class, function () use ($slug): ?array {
                $resource = $this->resources->resolve($slug);

                return $resource === null ? null : ResourceManifest::describe($resource);
            });
            if ($described !== null) {
                $resourcesPayload[] = $described;
            }
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
            $described = $this->guard('screen', $slug, $class, function () use ($slug): ?array {
                $screen = $this->admin->resolveScreen($slug);

                return $screen === null ? null : [
                    'slug' => $slug,
                    'name' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($screen->name()),
                    'description' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($screen->description()),
                    'permission' => $screen->permission(),
                ];
            });
            if ($described !== null) {
                $screensPayload[] = $described;
            }
        }

        $settingsPayload = [];
        foreach ($this->settings->all($panel) as $slug => $class) {
            $described = $this->guard('settings', $slug, $class, function () use ($slug): ?array {
                return $this->settings->resolve($slug)?->meta();
            });
            if ($described !== null) {
                $settingsPayload[] = $described;
            }
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
            $described = $this->guard('dashboard', $slug, $class, function () use ($class, $slug): ?array {
                $screen = app($class);
                if (! $screen instanceof \Dskripchenko\LaravelAdmin\Widget\DashboardScreen || ! $screen->canAccess()) {
                    return null;
                }

                return $screen->toManifest($slug);
            });
            if ($described !== null) {
                $dashboardsPayload[] = $described;
            }
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
            // What was left out, and why: for the administrator's eyes, so
            // only with app.debug on. The log has it either way.
            'diagnostics' => config('app.debug') ? array_map(
                static fn (array $s): array => ['kind' => $s['kind'], 'slug' => $s['slug'], 'class' => $s['class'], 'message' => $s['message']],
                $this->skipped,
            ) : [],
        ];
        $this->skipped = [];

        return $this->built[$memoKey] = [
            'version' => $this->buildVersion($payload),
            ...$payload,
        ];
    }

    /**
     * Runs $describe for one section of the manifest; when it throws, the
     * section is logged, remembered for `diagnostics` and left out.
     *
     * @param  callable(): ?array<string, mixed>  $describe
     * @return array<string, mixed>|null
     */
    private function guard(string $kind, string $slug, string $class, callable $describe): ?array
    {
        try {
            return $describe();
        } catch (Throwable $e) {
            Log::error("laravel-admin: the {$kind} [{$slug}] ({$class}) was left out of the manifest: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            $this->skipped[] = [
                'kind' => $kind,
                'slug' => $slug,
                'class' => $class,
                'error' => $e->getMessage(),
                'message' => (string) __('Раздел :slug (:class) не загрузился и скрыт: :error', [
                    'slug' => $slug,
                    'class' => $class,
                    'error' => $e::class.': '.$e->getMessage(),
                ]),
            ];

            return null;
        }
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
