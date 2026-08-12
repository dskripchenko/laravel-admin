<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Contracts;

/**
 * A UI element that serializes into a JSON schema.
 *
 * Layout and Field implement it. The SPA receives a tree of
 * {type, props, children} and draws the matching component through
 * LayoutRenderer or FieldRenderer.
 */
interface Renderable
{
    /**
     * Serializes into a JSON-friendly array for the SPA.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * The element's visibility — the canSee, onCreate and onUpdate flags.
     */
    public function isVisible(): bool;
}
