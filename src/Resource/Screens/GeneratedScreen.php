<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Базовый класс для авто-генерируемых Screen'ов поверх Resource (List/Create/Edit/View).
 *
 * Каждый Generated*Screen инстанцируется ScreenRegistry'ем с привязкой к
 * конкретному Resource'у, после чего его compile() даёт JSON-описание
 * страницы для SPA. Подклассы переопределяют только: kind(), name(), layout(),
 * commandBar() — общая обвязка (permissions, slug, type) живёт здесь.
 */
abstract class GeneratedScreen extends Screen
{
    public function __construct(protected readonly Resource $resource) {}

    /**
     * Идентификатор разновидности Screen'а: list|create|edit|view.
     */
    abstract public function kind(): string;

    /**
     * Slug для admin-API: `{resource-slug}.{kind}`.
     */
    public static function slug(): string
    {
        // GeneratedScreen без resource'а не используется — slug формируется из
        // kind() инстанса. Этот static метод нужен только для совместимости
        // со Screen::slug() — здесь вернёт class basename без 'Screen' суффикса.
        return parent::slug();
    }

    /**
     * Slug инстанса (с привязкой к Resource).
     */
    public function instanceSlug(): string
    {
        return $this->resource::slug().'.'.$this->kind();
    }

    /**
     * Required permission: маппится на Resource::permission().{kind}.
     *
     * @return list<string>|string|null
     */
    public function permission(): array|string|null
    {
        $base = $this->resource::permission();
        $action = match ($this->kind()) {
            'list', 'view' => 'view',
            'create' => 'create',
            'edit' => 'update',
            default => 'view',
        };

        return $base.'.'.$action;
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function compile(mixed ...$params): array
    {
        $base = parent::compile(...$params);

        return [
            ...$base,
            'type' => 'generated.'.$this->kind(),
            'resource_slug' => $this->resource::slug(),
        ];
    }

    /**
     * Загрузить запись по id или кинуть 404. Используется query() в
     * Edit/View screen'ах. Возвращает payload в форме `query()` (record + id).
     *
     * @return array<string, mixed>
     */
    protected function queryRecord(mixed $id): array
    {
        if ($id === null) {
            return ['record' => []];
        }

        $record = $this->resource->modelQuery()->find($id);
        if ($record === null) {
            throw new NotFoundHttpException("Record {$id} not found");
        }

        return ['record' => $record->toArray(), 'id' => $record->getKey()];
    }

    /**
     * Link «Назад» на index-страницу resource'а.
     */
    protected function buildBackLink(string $label = 'Назад'): Link
    {
        return Link::make($label)->href('/admin/resources/'.$this->resource::slug());
    }

    /**
     * Отфильтровать поля Resource'а по контексту (create|update).
     *
     * @return list<Field>
     */
    protected function filterFieldsBy(string $context): array
    {
        return array_values(array_filter(
            $this->resource->fields(),
            static fn (Field $f): bool => $f->appliesTo($context),
        ));
    }
}
