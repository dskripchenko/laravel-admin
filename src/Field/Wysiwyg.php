<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * WYSIWYG-редактор поверх Tiptap (на стороне SPA).
 *
 * Backend хранит HTML-string. Реальный рендеринг — Tiptap-обёртка
 * `@tiptap/vue-3` + `@tiptap/starter-kit` в SPA. Конкретные extensions
 * объявляются здесь — SPA подгружает только те, что разрешены.
 *
 * Presets:
 *   - 'minimal' — paragraph + bold + italic + link.
 *   - 'default' — minimal + heading + bullet/ordered list + code + blockquote
 *     + horizontal rule + image + link.
 *   - 'full' — default + table + textAlign + textColor + highlight +
 *     codeBlock + youtube + mention.
 *
 * Альтернативные WYSIWYG (TinyMCE / Quill) подключаются через sister-packs:
 * `laravel-admin-tinymce`, `laravel-admin-quill` — каждый регистрирует
 * собственный Field\TinyMce / Field\Quill (заменители Wysiwyg).
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
     * Default-extensions для preset'а.
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
     * Явный список extensions (override preset'а).
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
     * Toolbar config (`floating`, `sticky` или массив групп).
     *
     * @param  string|array<int, list<string>>  $toolbar
     */
    public function toolbar(string|array $toolbar): static
    {
        $this->attributes['toolbar'] = $toolbar;

        return $this;
    }

    /**
     * Включить image-upload через uploads-controller.
     *
     * @param  string  $endpoint  Custom endpoint (default: '/api/admin/uploads/image').
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
     * Включить/выключить server-side HTML-санитизацию через HtmlSanitizer.
     *
     * Default — true (защита от XSS). Disable только для trusted-content.
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
     * Получить эффективный список extensions (с учётом дефолта если не задан).
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
