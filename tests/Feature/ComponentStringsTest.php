<?php

declare(strict_types=1);

/**
 * Строки Vue-компонентов ядра.
 *
 * До 12.08.2026 их переводили выборочно: 94 строки в 32 файлах шли мимо
 * переводчика, и на английской панели соседствовали «Delete» и «Развернуть».
 * Хуже, чем неудобно: выглядит недоделанным ровно там, где всё остальное
 * переведено.
 *
 * Правило держится с двух сторон, потому что нарушить его можно двумя
 * способами: строку забыли обернуть — либо обернули, но не перевели. Второе
 * хуже: выглядит сделанным.
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
 * Отрезает хвостовой `//`-комментарий, не трогая `//` внутри строк и адресов
 * (`https://`).
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

/** Строки с кириллицей ВНЕ комментариев и вне переводчика. */
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
        // Хвостовой комментарий отрезаем и смотрим, осталась ли кириллица в
        // САМОМ коде. Простая проверка «строка начинается с //» здесь не
        // годится: `label: '',  // лейбл уже в шапке` — код и комментарий в
        // одной строке, и кириллица только во втором.
        $code = self_strip_trailing_comment($t);
        if (! preg_match('/[а-яА-Я]/u', $code)) {
            continue;
        }
        // Вырезаем ВЫЗОВЫ переводчика и смотрим, что осталось. Простое
        // «в строке есть tr( — пропускаем» прощало соседа: в
        // `x ? tRaw(...) : 'Привет'` вторая ветка уезжала непереведённой, и
        // тест этого не видел. Проверено сломом.
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
    // `tt(key, fallback)` отдаёт fallback, когда ключа нет. Значит отсутствие
    // ключа выглядит как работающий перевод — и всплывает только на чужом
    // языке. Четыре таких нашлись при первой же сплошной проверке.
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
