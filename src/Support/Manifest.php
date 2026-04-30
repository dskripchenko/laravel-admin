<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;

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
    ) {}

    /**
     * Собрать manifest для текущего пользователя и локали.
     *
     * На P1 фильтрация по permissions ещё не делается — все resource'ы видны.
     * На P2 будет приниматься AdminUser и фильтроваться.
     *
     * @return array<string, mixed>
     */
    public function build(string $locale = 'ru'): array
    {
        $resourcesPayload = [];
        foreach ($this->resources->all() as $slug => $class) {
            $resource = $this->resources->resolve($slug);
            if ($resource === null) {
                continue;
            }
            $resourcesPayload[] = $resource->meta();
        }

        $screensPayload = [];
        foreach ($this->screens->all() as $slug => $class) {
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

        $payload = [
            'locale' => $locale,
            'resources' => $resourcesPayload,
            'screens' => $screensPayload,
            'settings' => [],
            'dashboards' => [],
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
    public function version(string $locale = 'ru'): string
    {
        return $this->build($locale)['version'];
    }
}
