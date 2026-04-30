<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * `php artisan admin:install`
 *
 * Публикует config + миграции, опционально запускает миграции и создаёт
 * первого администратора.
 *
 * На фазе P0 это минимальная версия: ставит config + миграции + предлагает
 * запустить migrate. Интерактивный выбор auth-стратегии (dedicated vs shared)
 * — на фазе P2 (когда появится shared-режим). Сейчас всегда dedicated.
 */
final class InstallCommand extends Command implements PromptsForMissingInput
{
    /**
     * @var string
     */
    protected $signature = 'admin:install
                            {--force : Перезаписать существующие config/migrations}
                            {--no-migrate : Не запускать миграции автоматически}
                            {--no-user : Не предлагать создать первого администратора}';

    /**
     * @var string
     */
    protected $description = 'Установить laravel-admin: публикация config, миграций; опционально migrate и создание первого админа';

    public function handle(): int
    {
        $this->line('<info>laravel-admin</info> install');
        $this->newLine();

        $this->publishAssets();

        if (! $this->option('no-migrate')) {
            $this->maybeRunMigrations();
        }

        if (! $this->option('no-user')) {
            $this->maybeCreateAdmin();
        }

        $this->newLine();
        $this->info('Установка завершена.');
        $this->line('  → SPA-shell:        '.url((string) config('admin.path')));
        $this->line('  → API префикс:      '.url((string) config('admin.api_path')));
        $this->line('  → Создать админа:   php artisan admin:user');
        $this->line('  → Документация:     docs/ARCHITECTURE.md');

        return self::SUCCESS;
    }

    private function publishAssets(): void
    {
        $params = [
            '--provider' => 'Dskripchenko\\LaravelAdmin\\AdminServiceProvider',
        ];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        $this->components->task('Публикация config', function () use ($params): bool {
            return $this->callSilent('vendor:publish', $params + ['--tag' => 'admin-config']) === 0;
        });

        $this->components->task('Публикация миграций', function () use ($params): bool {
            return $this->callSilent('vendor:publish', $params + ['--tag' => 'admin-migrations']) === 0;
        });
    }

    private function maybeRunMigrations(): void
    {
        $run = confirm(
            label: 'Запустить миграции сейчас?',
            default: true,
        );

        if ($run) {
            $this->call('migrate');
        }
    }

    private function maybeCreateAdmin(): void
    {
        $create = confirm(
            label: 'Создать первого администратора?',
            default: true,
        );

        if (! $create) {
            return;
        }

        $name = text('Имя', required: true);
        $email = text('Email', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) === false ? 'Невалидный email' : null);
        $password = text('Пароль (минимум 8 символов)', required: true, validate: fn (string $v) => strlen($v) < 8 ? 'Слишком короткий' : null);

        $this->call('admin:user', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }
}
