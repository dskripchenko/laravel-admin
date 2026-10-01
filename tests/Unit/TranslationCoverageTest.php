<?php

declare(strict_types=1);

/*
 * Every Russian source string passed to __() in the core's PHP and Blade code
 * has an English translation — a missing key shows Russian in an English panel.
 */
it('has an English translation for every Russian __() string', function (): void {
    $en = json_decode((string) file_get_contents(__DIR__.'/../../resources/lang/en.json'), true);
    $files = array_merge(
        glob(__DIR__.'/../../src/{,*/,*/*/,*/*/*/}*.php', GLOB_BRACE) ?: [],
        glob(__DIR__.'/../../resources/views/{,*/}*.php', GLOB_BRACE) ?: [],
    );

    $missing = [];
    foreach ($files as $file) {
        preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/u", (string) file_get_contents($file), $m);
        foreach ($m[1] as $key) {
            $key = str_replace("\\'", "'", $key);
            if (preg_match('/\p{Cyrillic}/u', $key) === 1 && ! array_key_exists($key, $en)) {
                $missing[] = basename($file).': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});
