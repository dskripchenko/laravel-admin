<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Dskripchenko\LaravelAdmin\Support\PrebuiltAssets;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Symlinks public/vendor/admin to the prebuilt frontend inside the package
 * instead of copying it — for working on the package itself, where a fresh
 * `npm run build:app` should show up without republishing.
 */
final class LinkCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:link
                            {--force : Replace an existing public/vendor/admin}
                            {--relative : Create a relative symlink}';

    /**
     * @var string
     */
    protected $description = 'Symlink public/vendor/admin to the package\'s prebuilt frontend';

    public function handle(Filesystem $files): int
    {
        $target = realpath(PrebuiltAssets::sourcePath());
        if ($target === false || ! is_file($target.'/.vite/manifest.json')) {
            $this->components->error('The package has no prebuilt frontend. Run `npm run build:app` in the package.');

            return self::FAILURE;
        }

        $linkPath = PrebuiltAssets::publishedPath();

        if ($files->exists($linkPath) || is_link($linkPath)) {
            if (! $this->option('force')) {
                $this->components->error("{$linkPath} already exists. Use --force to replace it.");

                return self::FAILURE;
            }
            $files->isDirectory($linkPath) && ! is_link($linkPath)
                ? $files->deleteDirectory($linkPath)
                : $files->delete($linkPath);
        }

        $files->ensureDirectoryExists(dirname($linkPath));

        if ($this->option('relative')) {
            $files->relativeLink($target, $linkPath);
        } else {
            $files->link($target, $linkPath);
        }

        $this->components->info("Linked {$linkPath} → {$target}");

        return self::SUCCESS;
    }
}
