<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * A highlighted, copyable block of code.
 *
 *     Layout::code($snippet, 'php')->title('app/Admin/PostResource.php')->lineNumbers()
 */
final class Code extends Layout
{
    private string $code = '';

    public static function make(string $code, string $language = 'php'): self
    {
        $instance = new self;
        $instance->code = $code;
        $instance->props['language'] = $language;

        return $instance;
    }

    public function type(): string
    {
        return 'code';
    }

    /** A caption above the code: a file name, say. */
    public function title(string $title): self
    {
        $this->props['title'] = $title;

        return $this;
    }

    public function lineNumbers(bool $lineNumbers = true): self
    {
        $this->props['lineNumbers'] = $lineNumbers;

        return $this;
    }

    /** The height after which the block scrolls: 400 (px) or '50vh'. */
    public function maxHeight(int|string $height): self
    {
        $this->props['maxHeight'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }

    /** Wraps long lines instead of scrolling horizontally. */
    public function wrap(bool $wrap = true): self
    {
        $this->props['wrap'] = $wrap;

        return $this;
    }

    public function source(): string
    {
        return $this->code;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        // The code travels once, at the top level; see Markdown::toArray().
        $array['code'] = $this->code;

        return $array;
    }
}
