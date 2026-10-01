<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Resource\Resource as ResourceBase;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;

/**
 * Picks records of another registered resource through a dialog.
 *
 * Where RelationSelect reads an Eloquent model straight into a select, the
 * picker goes through the target RESOURCE: its index query, its search, its
 * filters, its pagination and its permissions. The dialog lists the records
 * the way the resource's own search endpoint returns them, with the title,
 * subtitle and preview the resource gives through Resource::pickerItem():
 *
 *     ResourcePicker::make('cover_id')->resource('media-library');
 *     ResourcePicker::make('gallery')->resource(MediaResource::class)->multiple()->maxItems(12);
 *
 * The value is the record's key — or, with multiple(), an ordered list of
 * keys. On save every key is checked against the target resource's
 * indexQuery(), so a form cannot attach a record the resource does not expose.
 */
class ResourcePicker extends Field
{
    public function fieldType(): string
    {
        return 'resource_picker';
    }

    /**
     * The target resource: its slug, or its class.
     *
     * @param  string|class-string<ResourceBase>  $resource
     */
    public function resource(string $resource): static
    {
        $this->attributes['resource'] = is_subclass_of($resource, ResourceBase::class)
            ? $resource::slug()
            : $resource;

        return $this;
    }

    /**
     * Several records, kept in the order the user arranges them.
     */
    public function multiple(bool $multiple = true): static
    {
        $this->attributes['multiple'] = $multiple;

        return $this;
    }

    /**
     * The most records a multiple picker accepts.
     */
    public function maxItems(int $max): static
    {
        $this->attributes['maxItems'] = max(1, $max);

        return $this;
    }

    /**
     * Fixed filter values sent with every search of the dialog, keyed by the
     * target resource's filter names. The user cannot change or clear them,
     * and the toolbar does not show those filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filters(array $filters): static
    {
        /** @var array<string, mixed> $current */
        $current = $this->attributes['filters'] ?? [];
        $this->attributes['filters'] = array_merge($current, $filters);

        return $this;
    }

    /**
     * Records per page of the dialog; 24 by default.
     */
    public function perPage(int $perPage): static
    {
        $this->attributes['perPage'] = max(1, $perPage);

        return $this;
    }

    /**
     * How the dialog lays the records out: 'grid' (preview tiles) or 'list'
     * (rows). By default it is a grid when the records have previews and a
     * list otherwise.
     */
    public function layout(string $layout): static
    {
        $this->attributes['layout'] = $layout === 'grid' ? 'grid' : 'list';

        return $this;
    }

    /**
     * The dialog's size: 'lg', 'xl' (the default) or 'full'.
     */
    public function dialogSize(string $size): static
    {
        $this->attributes['dialogSize'] = in_array($size, ['lg', 'xl', 'full'], true) ? $size : 'xl';

        return $this;
    }

    /**
     * Lets the user upload a new record from the dialog.
     *
     * The file goes as multipart form data to `$url` — a path under the
     * panel's API, like the core's own upload fields use — together with
     * `$data`. The response is the new record, or an object holding it under
     * `$responseKey`; its key gets selected. The button is shown only to users
     * holding `$permission`, the target's create permission by default.
     *
     * @param  array<string, scalar>  $data
     */
    public function uploadTo(
        string $url,
        ?string $permission = null,
        string $fileField = 'file',
        ?string $responseKey = null,
        array $data = [],
        ?string $accept = null,
    ): static {
        $this->attributes['upload'] = [
            'url' => $url,
            'permission' => $permission,
            'fileField' => $fileField,
            'responseKey' => $responseKey,
            'data' => $data,
            'accept' => $accept,
        ];

        return $this;
    }

    /**
     * The target resource's class, when it is registered.
     *
     * @return class-string<ResourceBase>|null
     */
    public function resourceClass(): ?string
    {
        $slug = $this->getAttribute('resource');
        if (! is_string($slug) || $slug === '' || ! app()->bound(ResourceRegistry::class)) {
            return null;
        }

        return app(ResourceRegistry::class)->get($slug);
    }

    public function toArray(): array
    {
        $out = parent::toArray();
        $class = $this->resourceClass();
        $base = $class !== null ? $class::permission() : null;
        $upload = $out['attributes']['upload'] ?? null;
        if (is_array($upload) && ($upload['permission'] ?? null) === null && $base !== null) {
            $upload['permission'] = $base.'.create';
            $out['attributes']['upload'] = $upload;
        }
        // The SPA hides the dialog from users who cannot browse the target.
        $out['attributes']['viewPermission'] = $base !== null ? $base.'.view' : null;
        $out['attributes']['multiple'] = (bool) ($out['attributes']['multiple'] ?? false);

        return $out;
    }
}
