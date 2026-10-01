<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Dskripchenko\LaravelAdmin\Support\PrebuiltAssets;
use Illuminate\Console\Command;

/**
 * Republishes the prebuilt admin frontend to public/vendor/admin.
 *
 * Run it after every package update — `admin:install` offers to add it to
 * composer.json's post-update-cmd — so the SPA matches the API it talks to.
 */
final class PublishCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:publish';

    /**
     * @var string
     */
    protected $description = 'Publish the prebuilt admin frontend to public/vendor/admin';

    public function handle(): int
    {
        if (is_link(PrebuiltAssets::publishedPath())) {
            $this->components->info('public/vendor/admin is a symlink (admin:link) — nothing to publish.');

            return self::SUCCESS;
        }

        $code = $this->callSilent('vendor:publish', [
            '--tag' => 'admin-assets',
            '--force' => true,
        ]);

        if ($code !== self::SUCCESS || ! PrebuiltAssets::isPublished()) {
            $this->components->error('The admin frontend could not be published.');

            return self::FAILURE;
        }

        $this->components->info('The admin frontend is published to public/vendor/admin.');

        return self::SUCCESS;
    }
}
