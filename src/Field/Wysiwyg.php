<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A WYSIWYG editor, built on Tiptap on the SPA's side.
 *
 * The backend stores an HTML string; the rendering is the
 * `@tiptap/vue-3` + `@tiptap/starter-kit` wrapper in the SPA. The extensions
 * are declared here, and the SPA loads only the permitted ones.
 *
 * The presets:
 *   - 'minimal' — paragraph, bold, italic, link.
 *   - 'default' — minimal plus heading, bullet and ordered lists, code,
 *     blockquote, horizontal rule, image and link.
 *   - 'full' — default plus table, textAlign, textColor, highlight, codeBlock,
 *     youtube and mention.
 *
 * The alternative editors, TinyMCE and Quill, arrive through the sister packs
 * `laravel-admin-tinymce` and `laravel-admin-quill`, each registering its own
 * Field\TinyMce or Field\Quill in place of Wysiwyg.
 */
final class Wysiwyg extends Field
{
    /** @var list<string> built-in presets */
    private const PRESETS = ['minimal', 'default', 'full'];

    public function fieldType(): string
    {
        return 'wysiwyg';
    }

    /**
     * The default extensions of a preset.
     *
     * @return list<string>
     */
    public static function defaultExtensions(string $preset = 'default'): array
    {
        return match ($preset) {
            'minimal' => ['paragraph', 'bold', 'italic', 'link'],
            'full' => [
                'paragraph', 'heading', 'bold', 'italic', 'underline', 'strike',
                'link', 'bulletList', 'orderedList', 'listItem', 'code', 'codeBlock',
                'blockquote', 'horizontalRule', 'image', 'table', 'tableRow',
                'tableCell', 'tableHeader', 'textAlign', 'textColor', 'highlight',
                'youtube', 'mention',
            ],
            default => [
                'paragraph', 'heading', 'bold', 'italic', 'link',
                'bulletList', 'orderedList', 'listItem', 'code', 'codeBlock',
                'blockquote', 'horizontalRule', 'image',
            ],
        };
    }

    public function preset(string $preset): static
    {
        if (! in_array($preset, self::PRESETS, true)) {
            throw new \InvalidArgumentException(
                'Wysiwyg preset must be one of: '.implode(', ', self::PRESETS),
            );
        }
        $this->attributes['extensions'] = self::defaultExtensions($preset);

        return $this;
    }

    /**
     * An explicit list of extensions, overriding the preset.
     *
     * @param  list<string>  $extensions
     */
    public function extensions(array $extensions): static
    {
        $this->attributes['extensions'] = $extensions;

        return $this;
    }

    public function withExtension(string $extension): static
    {
        $existing = (array) ($this->attributes['extensions'] ?? self::defaultExtensions());
        if (! in_array($extension, $existing, true)) {
            $existing[] = $extension;
        }
        $this->attributes['extensions'] = array_values($existing);

        return $this;
    }

    public function withoutExtension(string $extension): static
    {
        $existing = (array) ($this->attributes['extensions'] ?? self::defaultExtensions());
        $this->attributes['extensions'] = array_values(
            array_filter($existing, static fn ($e): bool => $e !== $extension),
        );

        return $this;
    }

    public function height(int|string $height): static
    {
        $this->attributes['height'] = $height;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->attributes['placeholder'] = $placeholder;

        return $this;
    }

    /**
     * The toolbar's configuration: `floating`, `sticky`, or an array of groups.
     *
     * @param  string|array<int, list<string>>  $toolbar
     */
    public function toolbar(string|array $toolbar): static
    {
        $this->attributes['toolbar'] = $toolbar;

        return $this;
    }

    /**
     * Turns on image uploads, through the uploads controller.
     *
     * @param  string  $endpoint  A custom endpoint; '/api/admin/uploads/image' by default.
     */
    public function uploadImages(bool $enable = true, string $endpoint = '/api/admin/uploads/image'): static
    {
        $this->attributes['uploadImages'] = $enable;
        if ($enable) {
            $this->attributes['uploadEndpoint'] = $endpoint;
        }

        return $this;
    }

    /**
     * Turns the server-side HTML sanitization through HtmlSanitizer on or off.
     *
     * It is on by default, as protection against XSS; switch it off only for
     * content you trust.
     */
    public function sanitize(bool $sanitize = true): static
    {
        $this->attributes['sanitize'] = $sanitize;

        return $this;
    }

    public function shouldSanitize(): bool
    {
        return (bool) ($this->attributes['sanitize'] ?? true);
    }

    /**
     * Returns the extensions actually in effect, falling back to the preset's.
     *
     * @return list<string>
     */
    public function getExtensions(): array
    {
        $configured = $this->attributes['extensions'] ?? null;
        if (is_array($configured) && $configured !== []) {
            /** @var list<string> $list */
            $list = array_values(array_filter($configured, 'is_string'));

            return $list;
        }

        return self::defaultExtensions();
    }
}
