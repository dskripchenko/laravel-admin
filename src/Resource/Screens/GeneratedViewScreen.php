<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Layout\Infolist;

/**
 * A record's read-only page, built from Resource::infolist().
 *
 * compile($id) loads the record and puts it into the state. The layout is an
 * infolist of the entries from Resource::infolist(), and the command bar holds
 * Edit, Delete and Back.
 */
final class GeneratedViewScreen extends GeneratedScreen
{
    public function kind(): string
    {
        return 'view';
    }

    public function name(): string
    {
        return $this->resource::label();
    }

    /**
     * @return array<string, mixed>
     */
    public function query(mixed ...$params): array
    {
        return $this->queryRecord($params[0] ?? null);
    }

    /**
     * @return list<\Dskripchenko\LaravelAdmin\Layout\Layout>
     */
    public function layout(): array
    {
        return [
            Infolist::make($this->resource->infolist()),
        ];
    }

    /**
     * @return list<Action>
     */
    public function commandBar(): array
    {
        return [
            Link::make('Редактировать')
                ->href('/admin/r/'.$this->resource::slug().'/{id}/edit')
                ->permission($this->resource::permission().'.update'),
            $this->buildBackLink(),
        ];
    }
}
