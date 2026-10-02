<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Illuminate\Console\Command;

/**
 * A plain alias for `admin:make-section --no-menu --no-role`: it generates
 * the resource alone, with no menu entry and no role. Internally it forwards to
 * MakeSectionCommand.
 */
final class MakeResourceCommand extends Command
{
    protected $signature = 'admin:make-resource
                            {--force : Overwrite an existing resource}';

    protected $description = 'Generate a resource (admin:make-section without the menu item and role)';

    public function handle(): int
    {
        $this->info('A shorter version of admin:make-section: no menu item and no role are created.');
        $this->info('The full wizard: php artisan admin:make-section');

        return $this->call('admin:make-section', [
            '--force' => $this->option('force'),
        ]);
    }
}
