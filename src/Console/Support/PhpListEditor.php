<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

/**
 * Appends an item to a PHP array literal inside a source file, keeping the
 * file's own formatting: a one-line list stays on one line, a multi-line list
 * gets a new line with the indentation of its items, and the comments inside
 * the list are left where they are.
 *
 * It works on PHP tokens, so brackets inside strings and comments never
 * confuse it.
 */
final class PhpListEditor
{
    /**
     * The offset of the `[` opening the value of a top-level key of the file's
     * returned array — `'plugins' => [` in config/admin.php — or null.
     */
    public static function findConfigKeyList(string $source, string $key): ?int
    {
        $tokens = self::tokens($source);
        $depth = 0;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            [$id, $text] = $tokens[$i];
            if ($text === '[') {
                $depth++;

                continue;
            }
            if ($text === ']') {
                $depth--;

                continue;
            }
            if ($depth !== 1 || $id !== T_CONSTANT_ENCAPSED_STRING || trim($text, '\'"') !== $key) {
                continue;
            }
            $next = self::nextSignificant($tokens, $i);
            if ($next === null || $tokens[$next][0] !== T_DOUBLE_ARROW) {
                continue;
            }
            $value = self::nextSignificant($tokens, $next);
            if ($value !== null && $tokens[$value][1] === '[') {
                return $tokens[$value][2];
            }
        }

        return null;
    }

    /**
     * The offset of the `[` in the first `$var->method([` call, or null.
     */
    public static function findMethodCallList(string $source, string $method): ?int
    {
        $tokens = self::tokens($source);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] !== T_OBJECT_OPERATOR) {
                continue;
            }
            $name = self::nextSignificant($tokens, $i);
            if ($name === null || $tokens[$name][0] !== T_STRING || $tokens[$name][1] !== $method) {
                continue;
            }
            $paren = self::nextSignificant($tokens, $name);
            if ($paren === null || $tokens[$paren][1] !== '(') {
                continue;
            }
            $bracket = self::nextSignificant($tokens, $paren);
            if ($bracket !== null && $tokens[$bracket][1] === '[') {
                return $tokens[$bracket][2];
            }
        }

        return null;
    }

    /**
     * Appends $item (raw PHP, `Foo::class`) to the list opening at $open.
     * Returns null when no list opens there.
     */
    public static function append(string $source, int $open, string $item): ?string
    {
        $tokens = self::tokens($source);
        $start = null;
        foreach ($tokens as $index => $token) {
            if ($token[2] === $open && $token[1] === '[') {
                $start = $index;
                break;
            }
        }
        if ($start === null) {
            return null;
        }

        // The matching `]`, and the last token of code before it.
        $depth = 0;
        $close = null;
        $last = $start;
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $text = $tokens[$i][1];
            if ($text === '[' || $text === '(' || $text === '{') {
                $depth++;
            } elseif ($text === ']' || $text === ')' || $text === '}') {
                $depth--;
                if ($depth === 0) {
                    $close = $i;
                    break;
                }
            }
            if (! in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $last = $i;
            }
        }
        if ($close === null) {
            return null;
        }

        $closeAt = $tokens[$close][2];
        $inner = substr($source, $open + 1, $closeAt - $open - 1);

        // A one-line list stays on one line.
        if (! str_contains($inner, "\n")) {
            $items = rtrim(trim($inner), ',');
            $list = $items === '' ? $item : rtrim($items).', '.$item;

            return substr($source, 0, $open + 1).$list.substr($source, $closeAt);
        }

        $lineStart = (int) strrpos(substr($source, 0, $closeAt), "\n");
        $closeIndent = substr($source, $lineStart + 1, $closeAt - $lineStart - 1);
        $itemIndent = self::itemIndent($inner) ?? (trim($closeIndent) === '' ? $closeIndent : '').'    ';

        $lastEnd = $tokens[$last][2] + strlen($tokens[$last][1]);
        $needsComma = $last !== $start && $tokens[$last][1] !== ',';

        if (trim($closeIndent) === '') {
            // `]` on a line of its own: the item goes on a new line above it.
            $source = substr($source, 0, $lineStart + 1).$itemIndent.$item.",\n".substr($source, $lineStart + 1);

            return $needsComma
                ? substr($source, 0, $lastEnd).','.substr($source, $lastEnd)
                : $source;
        }

        // `]` right after the last item: keep it there.
        return substr($source, 0, $lastEnd)
            .($needsComma ? ',' : '')."\n".$itemIndent.$item
            .substr($source, $lastEnd);
    }

    private static function itemIndent(string $inner): ?string
    {
        foreach (explode("\n", $inner) as $index => $line) {
            if ($index === 0 || trim($line) === '') {
                continue;
            }

            return (string) preg_replace('/^(\s*).*$/s', '$1', $line);
        }

        return null;
    }

    /**
     * @return list<array{0: int|null, 1: string, 2: int}> [token id, text, offset]
     */
    private static function tokens(string $source): array
    {
        $result = [];
        $offset = 0;
        foreach (token_get_all($source) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;
            $result[] = [$id, $text, $offset];
            $offset += strlen($text);
        }

        return $result;
    }

    /**
     * @param  list<array{0: int|null, 1: string, 2: int}>  $tokens
     */
    private static function nextSignificant(array $tokens, int $from): ?int
    {
        $count = count($tokens);
        for ($i = $from + 1; $i < $count; $i++) {
            if (! in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }
}
