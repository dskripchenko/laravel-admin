<?php

declare(strict_types=1);

/**
 * The strings of the core's Vue components.
 *
 * Until 12.08.2026 they were translated selectively: 94 strings across 32 files
 * went past the translator, and on an English panel "Delete" stood next to
 * "Развернуть". Worse than inconvenient: it looks unfinished exactly where
 * everything else is translated.
 *
 * The rule is held from two sides, because it can be broken in two ways: the
 * string was not wrapped, or it was wrapped but not translated. The second is
 * worse: it looks done.
 */
$vueFiles = static function (): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/ts'));
    foreach (new RegexIterator($it, '/\.vue$/') as $file) {
        $out[] = (string) $file;
    }
    sort($out);

    return $out;
};

/**
 * Cuts off a trailing `//` comment without touching a `//` inside strings and
 * addresses (`https://`).
 */
function self_strip_trailing_comment(string $line): string
{
    $len = mb_strlen($line);
    $quote = null;

    for ($i = 0; $i < $len - 1; $i++) {
        $ch = mb_substr($line, $i, 1);

        if ($quote !== null) {
            if ($ch === $quote) {
                $quote = null;
            }

            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;

            continue;
        }
        if ($ch === '/' && mb_substr($line, $i + 1, 1) === '/') {
            return mb_substr($line, 0, $i);
        }
    }

    return $line;
}

/** The strings with Cyrillic OUTSIDE the comments and outside the translator. */
$unwrapped = static function (string $source): array {
    $out = [];
    $inBlock = false;

    foreach (explode("\n", $source) as $i => $line) {
        $t = trim($line);

        if (str_starts_with($t, '/*') || str_starts_with($t, '<!--')) {
            $inBlock = true;
        }
        if ($inBlock) {
            if (str_contains($t, '*/') || str_contains($t, '-->')) {
                $inBlock = false;
            }

            continue;
        }
        if (str_starts_with($t, '//') || str_starts_with($t, '*')) {
            continue;
        }
        if (! preg_match('/[а-яА-Я]/u', $line)) {
            continue;
        }
        // We cut off the trailing comment and look at whether any Cyrillic is
        // left in the CODE itself. A simple "the line starts with //" check is
        // no good here: `label: '',  // лейбл уже в шапке` — the code and the
        // comment share a line and the Cyrillic is only in the latter.
        $code = self_strip_trailing_comment($t);
        if (! preg_match('/[а-яА-Я]/u', $code)) {
            continue;
        }
        // We cut out the translator's CALLS and look at what is left. A simple
        // "the line contains tr( — skip it" forgave the neighbour: in
        // `x ? tRaw(...) : 'Привет'` the second branch went out untranslated and
        // the test did not see it. Verified by breaking it.
        $code = (string) preg_replace('/\\btt?(?:r|Raw)?\\([^)]*\\)/u', '', $code);
        if (! preg_match('/[а-яА-Я]/u', $code)) {
            continue;
        }

        $out[] = ($i + 1).': '.mb_substr($t, 0, 70);
    }

    return $out;
};

it('в компонентах ядра нет строк мимо переводчика', function () use ($vueFiles, $unwrapped): void {
    $found = [];

    foreach ($vueFiles() as $file) {
        foreach ($unwrapped((string) file_get_contents($file)) as $line) {
            $found[] = basename($file).':'.$line;
        }
    }

    expect($found)->toBe([], 'мимо переводчика: '.implode(' | ', $found));
});

it('каждая обёрнутая строка переведена на английский', function () use ($vueFiles): void {
    $en = json_decode((string) file_get_contents(__DIR__.'/../../resources/lang/en.json'), true);
    $missing = [];

    foreach ($vueFiles() as $file) {
        preg_match_all("/\\btr(?:Raw)?\\('((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file), $m);
        foreach ($m[1] as $raw) {
            $key = str_replace("\\'", "'", $raw);
            if (preg_match('/[а-яА-Я]/u', $key) && ! array_key_exists($key, $en)) {
                $missing[] = basename($file).': '.mb_substr($key, 0, 50);
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([], 'нет перевода: '.implode(' | ', array_unique($missing)));
});

it('ключи tt() есть в словарях, иначе всплывает русский запасной вариант', function () use ($vueFiles): void {
    // `tt(key, fallback)` returns the fallback when the key is missing. So a
    // missing key looks like a working translation — and surfaces only in
    // another language. Four of those turned up in the very first full sweep.
    $ru = (string) file_get_contents(__DIR__.'/../../resources/lang/ru/admin.php');
    $en = (string) file_get_contents(__DIR__.'/../../resources/lang/en/admin.php');
    $missing = [];

    foreach ($vueFiles() as $file) {
        preg_match_all("/\\btt\\('([a-z0-9_.]+)'/", (string) file_get_contents($file), $m);
        foreach ($m[1] as $key) {
            $leaf = "'".mb_substr($key, mb_strrpos($key, '.') + 1)."'";
            if (! str_contains($ru, $leaf) || ! str_contains($en, $leaf)) {
                $missing[] = $key;
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([], 'нет в словарях: '.implode(', ', array_unique($missing)));
});
