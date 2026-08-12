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
                            {--force : Перезаписать существующий Resource}';

    protected $description = 'Сгенерировать Resource (subset мастера admin:make-section без меню и role)';

    public function handle(): int
    {
        $this->info('Это упрощённая версия мастера. Меню и роль не создаются.');
        $this->info('Полный мастер: php artisan admin:make-section');

        return $this->call('admin:make-section', [
            '--force' => $this->option('force'),
        ]);
    }
}
