<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Illuminate\Console\Command;

/**
 * Простой alias для `admin:make-section --no-menu --no-role` — генерирует
 * только Resource, без меню и role'и. Внутри переадресуется в
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
