<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * What an empty list says: a title, a hint and an action.
 *
 *     public function emptyState(): ?EmptyState
 *     {
 *         return EmptyState::make('Городов пока нет', 'Города используются в карточках офисов.', 'Добавить город');
 *     }
 *
 *     Layout::resourceIndex(CallbackResource::class)->scope('new')
 *         ->emptyState('Новых звонков нет', 'Счётчик в меню обнулён.',
 *             Link::make('Открыть «В работе»')->href('/admin/screens/calls?tab=work'));
 *
 * The action is
 *   - null — «Создать» of the resource, for a user who may create (the default);
 *   - a string — the same button under that caption («Добавить город»);
 *   - an Action — a link or any action of the panel, run as the list runs its own;
 *   - false — no action.
 *
 * The «nothing found» state of a filtered list has its own title and hint
 * (`noResults()`); its action is always «Сбросить фильтры».
 */
final class EmptyState
{
    private ?string $noResultsTitle = null;

    private ?string $noResultsDescription = null;

    private function __construct(
        private readonly ?string $title,
        private readonly ?string $description,
        private readonly string|Action|false|null $action,
    ) {}

    public static function make(?string $title = null, ?string $description = null, string|Action|false|null $action = null): self
    {
        return new self($title, $description, $action);
    }

    /**
     * The title and hint of a list whose search or filters match nothing.
     */
    public function noResults(?string $title, ?string $description = null): self
    {
        $this->noResultsTitle = $title;
        $this->noResultsDescription = $description;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $action = $this->action;
        if ($action instanceof Action) {
            $action = $action->isVisible() ? $action->toArray() : false;
        } elseif (is_string($action)) {
            $action = ['create' => Localize::string($action)];
        }

        return [
            'title' => $this->title === null ? null : Localize::string($this->title),
            'description' => $this->description === null ? null : Localize::string($this->description),
            // null — the default «Создать»; false — none; {create: label}; an action.
            'action' => $action,
            'noResultsTitle' => $this->noResultsTitle === null ? null : Localize::string($this->noResultsTitle),
            'noResultsDescription' => $this->noResultsDescription === null ? null : Localize::string($this->noResultsDescription),
        ];
    }
}
