<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use InvalidArgumentException;

/**
 * A document drawn in the browser from its source text: a diagram, a BPMN or
 * DMN model, a draw.io file, an AsyncAPI document, a kanban board or an event
 * calendar. What these layouts share is where the text comes from:
 *
 *     Layout::diagram('schema')              // $state['schema']
 *     Layout::diagram("flowchart LR\nA-->B") // inline
 *     Layout::bpmn('')->url('/files/order.bpmn')
 *
 * A string that is an identifier (`/^[A-Za-z_][\w.-]*$/`) is a key of the
 * screen's state, read from the form or the infolist record and followed as
 * it changes; anything else is the content itself. url() loads the content
 * in the browser instead, from an address of the same site, with the user's
 * session.
 */
abstract class DocumentLayout extends Layout
{
    /** A state key: a letter or an underscore, then letters, digits, `_`, `.` or `-`. */
    public const STATE_KEY = '/^[A-Za-z_][\w.-]*$/';

    /** The inline content. */
    protected ?string $content = null;

    /** The state key holding the content. */
    protected ?string $source = null;

    /** The address the content is loaded from. */
    protected ?string $url = null;

    final public function __construct() {}

    /**
     * @param  string  $source  A state key or the content itself.
     */
    public static function make(string $source): static
    {
        $instance = new static;
        if (trim($source) === '') {
            throw new InvalidArgumentException("Layout::{$instance->type()}() needs a state key or the content");
        }
        if (preg_match(self::STATE_KEY, $source) === 1) {
            $instance->source = $source;
        } else {
            $instance->content = $source;
        }

        return $instance;
    }

    /**
     * Loads the content in the browser from this address — a path of the
     * panel's site (`/files/order.bpmn`) or a URL of the same origin — with
     * the user's session. It replaces the state key or the inline content.
     */
    public function url(string $url): static
    {
        $url = trim($url);
        $sameSitePath = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        if (! $sameSitePath && preg_match('#^https?://[^/\s]+#i', $url) !== 1) {
            throw new InvalidArgumentException("Layout::{$this->type()}()->url() takes a path or an http(s) URL, `{$url}` given");
        }
        $this->url = $url;
        $this->source = null;
        $this->content = null;

        return $this;
    }

    /** A caption above the document. */
    public function title(string $title): static
    {
        $this->props['title'] = $title;

        return $this;
    }

    /** The height of the box: 560 (px) or '70vh'. */
    public function height(int|string $height): static
    {
        if (is_int($height)) {
            if ($height <= 0) {
                throw new InvalidArgumentException("Layout::{$this->type()}()->height() must be positive, {$height} given");
            }
            $this->props['height'] = $height.'px';

            return $this;
        }
        $height = trim($height);
        if (preg_match('/^\d+(\.\d+)?(px|%|vh|vw|dvh|svh|lvh|em|rem)$/', $height) !== 1) {
            throw new InvalidArgumentException("Layout::{$this->type()}()->height() takes pixels or a CSS length like '70vh', `{$height}` given");
        }
        $this->props['height'] = $height;

        return $this;
    }

    /** The state key holding the content, or null. */
    public function source(): ?string
    {
        return $this->source;
    }

    /** The inline content, or null. */
    public function content(): ?string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // The content travels once, at the top level, like Markdown's text.
        $array['content'] = $this->content;
        $array['source'] = $this->source;
        $array['url'] = $this->url;

        return $array;
    }
}
