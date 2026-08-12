<?php

declare(strict_types=1);

/**
 * Комментарии в коде — по-английски. Храповик, а не запрет.
 *
 * Долг накоплен исторически: на 12.08.2026 — 3930 строк в 399 файлах. Разом он
 * не переводится, поэтому тест держит не ноль, а ЗАПИСАННЫЙ уровень: файл не
 * может стать хуже, новый файл обязан быть чистым, а переведённый файл
 * фиксируется на новом уровне.
 *
 * Так долг может только убывать. Обычный запрет «ноль русских комментариев»
 * здесь был бы красным с первого дня и перестал бы что-либо значить — как
 * ночной regression, который никогда не был зелёным.
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

it('русских комментариев не становится больше', function () use ($countRussian, $sources): void {
    $root = dirname(__DIR__, 2);
    $baseline = json_decode((string) file_get_contents($root.'/tests/comment-debt.json'), true);
    $worse = [];

    foreach ($sources() as $rel) {
        $now = $countRussian($root.'/'.$rel);
        $was = $baseline[$rel] ?? 0;

        if ($now > $was) {
            $worse[] = "{$rel}: было {$was}, стало {$now}";
        }
    }

    expect($worse)->toBe([], "стало хуже:\n".implode("\n", $worse)
        ."\n\nКомментарии пишутся по-английски. Если файл переведён — обновите tests/comment-debt.json.");
});

it('в базе долга нет файлов, которые уже переведены', function () use ($countRussian): void {
    // Иначе храповик разболтается: файл переведён, а разрешение осталось — и
    // в него можно молча вернуть русский комментарий.
    $root = dirname(__DIR__, 2);
    $baseline = json_decode((string) file_get_contents($root.'/tests/comment-debt.json'), true);
    $stale = [];

    foreach ($baseline as $rel => $allowed) {
        if (! is_file($root.'/'.$rel)) {
            $stale[] = "{$rel}: файла нет";

            continue;
        }
        $now = $countRussian($root.'/'.$rel);
        if ($now < $allowed) {
            $stale[] = "{$rel}: разрешено {$allowed}, осталось {$now}";
        }
    }

    expect($stale)->toBe([], "база долга отстала:\n".implode("\n", $stale)
        ."\n\nОбновите tests/comment-debt.json под текущее состояние.");
});
