<?php

declare(strict_types=1);

/*
 * The console UX is English: every prompt, hint, task line and error of the
 * artisan commands is written in English. admin:make-section once asked its
 * questions in Russian; this keeps Cyrillic out of the commands for good.
 */
it('keeps the artisan commands in English', function (): void {
    $root = dirname(__DIR__, 2).'/src/Console';
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        foreach (file($file->getPathname()) as $number => $line) {
            if (preg_match('/\p{Cyrillic}/u', $line) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1).':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
