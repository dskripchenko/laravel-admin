<?php

declare(strict_types=1);

/**
 * Комментарии в коде — по-английски.
 *
 * Долг был накоплен исторически: на 12.08.2026 — 3930 строк в 399 файлах.
 * Разом он не переводился, поэтому тест держал не ноль, а записанный уровень
 * (`tests/comment-debt.json`): файл не мог стать хуже, новый файл обязан был
 * быть чистым, а переведённый фиксировался на новом уровне.
 *
 * 12.08.2026 долг закрыт целиком — храповик стал обычным запретом, а база
 * долга удалена за ненадобностью.
 *
 * Правило и исключения: см. память feedback_code_comments_english. Коротко:
 * `docs/**`, ADR и тексты тестовых сообщений остаются на русском.
 */
$countRussian = static function (string $path): int {
    $n = 0;

    foreach (explode("\n", (string) file_get_contents($path)) as $line) {
        $t = ltrim($line);
        if (! str_starts_with($t, '//') && ! str_starts_with($t, '*') && ! str_starts_with($t, '/*') && ! str_starts_with($t, '#')) {
            continue;
        }
        if (preg_match('/[а-яА-Я]/u', $line)) {
            $n++;
        }
    }

    return $n;
};

$sources = static function (): array {
    $root = dirname(__DIR__, 2);
    $out = [];

    foreach (['src', 'resources/ts', 'config', 'database'] as $dir) {
        if (! is_dir($root.'/'.$dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir));
        foreach (new RegexIterator($it, '/\.(php|ts|vue)$/') as $file) {
            $out[] = str_replace($root.'/', '', (string) $file);
        }
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
