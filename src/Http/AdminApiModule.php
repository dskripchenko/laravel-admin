<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelApi\Components\BaseModule;

/**
 * Admin API module — точка входа для laravel-api.
 *
 * Регистрирует одну API-версию `admin` на классе AdminApi. Сегмент `admin`
 * в URL — внутренний идентификатор, не публично-стабильная версия. Семвер
 * привязан к версии admin-пакета (см. ARCHITECTURE.md п.13.12).
 */
final class AdminApiModule extends BaseModule
{
    /**
     * @return array<string, class-string<\Dskripchenko\LaravelApi\Components\BaseApi>>
     */
    public function getApiVersionList(): array
    {
        return [
            'admin' => AdminApi::class,
        ];
    }
}
