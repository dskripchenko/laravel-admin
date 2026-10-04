<?php

declare(strict_types=1);

/*
 * Every key of config/admin.php must be read somewhere. A key nobody reads is
 * a promise the package does not keep: a host sets it and nothing happens.
 */
it('reads every key of config/admin.php', function (): void {
    // Keys read as part of a whole array rather than by their dotted path.
    $readAsArray = [
        'brand.',               // config('admin.brand') → the shell and the SPA bootstrap
        'notice.',              // config('admin.notice') → the shell banner
        'exports.pdf.options.', // config('admin.exports.pdf.options.<driver>') → the renderer
        'roles',                // read by the RoleResource of dskripchenko/laravel-admin-starter
    ];

    $flatten = static function (array $config, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($config as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $keys[] = $path;
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                array_push($keys, ...$flatten($value, $path));
            }
        }

        return $keys;
    };

    $root = dirname(__DIR__, 2);
    $sources = '';
    foreach (['src', 'routes', 'resources/views'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources .= file_get_contents($file->getPathname());
            }
        }
    }

    $unread = array_values(array_filter(
        $flatten(require $root.'/config/admin.php'),
        static function (string $key) use ($sources, $readAsArray): bool {
            foreach ($readAsArray as $prefix) {
                if ($key === rtrim($prefix, '.') || str_starts_with($key, $prefix)) {
                    return false;
                }
            }

            return ! str_contains($sources, 'admin.'.$key);
        },
    ));

    expect($unread)->toBe([]);
});
