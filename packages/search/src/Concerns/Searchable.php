<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Concerns;

/**
 * Trait для Resource'ов, которые должны участвовать в global search.
 *
 * Подключается в Resource:
 *
 *     class UserResource extends Resource {
 *         use Searchable;
 *         public function searchableFields(): array { return ['name', 'email']; }
 *         public function searchTitle(): string { return 'name'; }
 *     }
 *
 * Все методы trait'а имеют sane defaults — Resource переопределяет только
 * нужные.
 */
trait Searchable
{
    /**
     * Поля для LIKE/Scout-поиска. Дублируется с Resource::searchableFields(),
     * но trait переопределяет (search-нужны более широкие fields обычно).
     *
     * @return list<string>
     */
    abstract public function searchableFields(): array;

    /**
     * Имя поля, отображаемого как title в результате (default 'name'/'title'/
     * 'id' — пробуем по очереди).
     */
    public function searchTitle(): string
    {
        return 'name';
    }

    /**
     * Имя поля для subtitle (мелкого пояснения — обычно email/slug/etc).
     * null — не показывать subtitle.
     */
    public function searchSubtitle(): ?string
    {
        return null;
    }

    /**
     * Lucide-icon name для группы. По умолчанию из Resource::$icon.
     */
    public function searchIcon(): ?string
    {
        /* @phpstan-ignore property.notFound */
        return static::$icon ?? 'circle';
    }

    /**
     * URL/route для выбранного результата. По умолчанию — edit-страница
     * Resource'а.
     *
     * @param  array<string, mixed>  $row
     */
    public function searchUrl(array $row): string
    {
        $id = $row['id'] ?? null;
        if ($id === null) {
            return '';
        }
        $slug = static::slug();

        return "/admin/r/$slug/$id/edit";
    }

    /**
     * Приоритет в выдаче (выше — раньше). По умолчанию 0.
     */
    public function searchPriority(): int
    {
        return 0;
    }
}
