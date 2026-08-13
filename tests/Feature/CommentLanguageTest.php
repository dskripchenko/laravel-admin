<?php

declare(strict_types=1);

/**
 * Comments in the code are written in English.
 *
 * The debt was accumulated historically: on 12.08.2026 it was 3930 lines across
 * 399 files. It was not translated in one go, so the test used to hold not zero
 * but the recorded level (`tests/comment-debt.json`): a file could not get
 * worse, a new file had to be clean, and a translated one was pinned at its new
 * level.
 *
 * On 12.08.2026 the debt was closed in full — the ratchet became an ordinary ban
 * and the debt baseline was deleted as no longer needed.
 *
 * The rule and its exceptions: see the memory feedback_code_comments_english. In
 * short: `docs/**`, the ADRs and the texts of the test messages stay in Russian.
 */
/**
 * A QUOTATION is not prose. An English comment may cite a Russian string — a
 * label the defect is addressed by, a search query, a value from a fixture — and
 * translating the citation would lose what it points at. So quoted spans are cut
 * out before the check; whatever Cyrillic is left is the comment's own text.
 */
$countRussian = static function (string $path): int {
    $n = 0;

    foreach (explode("\n", (string) file_get_contents($path)) as $line) {
        // `|` is the Laravel config docblock: the whole section header and its
        // explanation sit on lines starting with it, and while they were not
        // counted `config/admin.php` read as clean with 37 Russian lines in it.
        $t = ltrim($line);
        if (! str_starts_with($t, '//') && ! str_starts_with($t, '*') && ! str_starts_with($t, '/*')
            && ! str_starts_with($t, '#') && ! str_starts_with($t, '|')) {
            continue;
        }
        $prose = preg_replace('/"[^"]*"|\'[^\']*\'|`[^`]*`|«[^»]*»/u', '', $line);
        if (preg_match('/[а-яА-Я]/u', (string) $prose)) {
            $n++;
        }
    }

    return $n;
};

/**
 * Everything a developer reads, not only the package's own code: the tests, the
 * fixtures, the blade views and the root-level configs count too. The narrow
 * list they used to be missing from let a translated `src/**` read as a closed
 * debt while 335 lines were still sitting in `tests/**` and the build configs.
 */
$sources = static function (): array {
    $root = dirname(__DIR__, 2);
    $out = [];
    $dirs = ['config', 'database', 'resources', 'routes', 'src', 'tests', '.storybook'];

    foreach ($dirs as $dir) {
        if (! is_dir($root.'/'.$dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir));
        foreach (new RegexIterator($it, '/\.(php|ts|js|mjs|cjs|vue|py)$/') as $file) {
            $rel = str_replace($root.'/', '', (string) $file);
            if (str_contains($rel, '/node_modules/')) {
                continue;
            }
            $out[] = $rel;
        }
    }

    foreach ((array) glob($root.'/*.{php,js,mjs,cjs,ts,py}', GLOB_BRACE) as $file) {
        $out[] = str_replace($root.'/', '', (string) $file);
    }
    sort($out);

    return $out;
};

it('русских комментариев в коде нет', function () use ($countRussian, $sources): void {
    $root = dirname(__DIR__, 2);
    $dirty = [];

    foreach ($sources() as $rel) {
        $n = $countRussian($root.'/'.$rel);

        if ($n > 0) {
            $dirty[] = "{$rel}: {$n}";
        }
    }

    expect($dirty)->toBe([], "русские комментарии:\n".implode("\n", $dirty)
        ."\n\nКомментарии в коде пишутся по-английски (docs/** и ADR — исключение).");
});
