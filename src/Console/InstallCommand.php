<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * `php artisan admin:install`
 *
 * Takes a Laravel application to a working admin:
 *
 *  1. publishes the config and the migrations;
 *  2. publishes the prebuilt frontend to public/vendor/admin — no Node, no
 *     build step — or, with `--custom-build`, wires an entry of the host's own
 *     into its Vite build instead (for custom fields, widgets and pages);
 *  3. runs the migrations and creates the first administrator;
 *  4. offers to add `admin:publish` to composer.json's post-update-cmd, so the
 *     frontend follows package updates.
 *
 * With `--shared` the administrators are the application's own users: the
 * config is pointed at the default guard, its provider, model and password
 * broker, and a migration adding the admin's columns to that users table is
 * published next to the others.
 *
 * Every question has a flag, so it also runs non-interactively.
 */
final class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:install
                            {--custom-build : Build the frontend with the host\'s Vite instead of the prebuilt bundle}
                            {--shared : Sign in the application\'s existing users instead of a separate admin_users table}
                            {--force : Overwrite published config and migrations}
                            {--no-migrate : Do not run the migrations}
                            {--no-user : Do not offer to create the first administrator}
                            {--no-composer-hook : Do not add admin:publish to composer.json post-update-cmd}';

    /**
     * @var string
     */
    protected $description = 'Install laravel-admin: config, migrations, frontend, first administrator';

    private const ENTRY = 'resources/js/admin.js';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Installing laravel-admin');

        $this->publish('admin-config', 'Config');
        $this->publish('admin-migrations', 'Migrations');

        if ($this->option('shared')) {
            $this->configureShared($files);
        }

        if ($this->option('custom-build')) {
            $this->wireCustomBuild($files);
        } else {
            $this->components->task('Frontend (prebuilt)', fn (): bool => $this->callSilent('admin:publish') === self::SUCCESS);
            $this->maybeAddComposerHook($files);
        }

        if (! $this->option('no-migrate') && $this->confirms('Run the migrations now?', true)) {
            $this->call('migrate');
        }

        if (! $this->option('no-user') && $this->confirms('Create the first administrator?', true)) {
            $this->option('shared') ? $this->createSharedAdmin() : $this->createAdmin();
        }

        $this->newLine();
        $this->components->info('Done.');
        $this->components->twoColumnDetail('Admin', url((string) config('admin.path', 'admin')));
        $this->components->twoColumnDetail('More administrators', 'php artisan admin:user --super');
        $this->components->twoColumnDetail('Docs', 'https://github.com/dskripchenko/laravel-admin/blob/main/docs/en/getting-started.md');

        return self::SUCCESS;
    }

    private function publish(string $tag, string $label): void
    {
        $params = ['--tag' => $tag];
        if ($this->option('force')) {
            $params['--force'] = true;
        }
        $this->components->task($label, fn (): bool => $this->callSilent('vendor:publish', $params) === self::SUCCESS);
    }

    /**
     * Generates the host's admin entry, adds it to the Vite inputs and points
     * config('admin.assets') at the Vite manifest.
     */
    private function wireCustomBuild(Filesystem $files): void
    {
        $entry = base_path(self::ENTRY);
        $this->components->task('Entry '.self::ENTRY, function () use ($files, $entry): bool {
            if ($files->exists($entry) && ! $this->option('force')) {
                return true;
            }
            $files->ensureDirectoryExists(dirname($entry));
            $files->put($entry, (string) $files->get(dirname(__DIR__, 2).'/resources/stubs/admin/admin.js.stub'));

            return true;
        });

        $viteWired = $this->addViteInput($files);
        $this->components->task('Vite input', fn (): bool => $viteWired);

        $this->components->task('config/admin.php assets', fn (): bool => $this->pointConfigAtVite($files));

        $this->newLine();
        $this->line('  Install the frontend packages and build:');
        $this->line('    npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg');
        $this->line('    npm run build');
        if (! $viteWired) {
            $this->line("  Add '".self::ENTRY."' to the laravel() input list in your vite.config.js.");
        }
    }

    private function addViteInput(Filesystem $files): bool
    {
        foreach (['vite.config.js', 'vite.config.ts', 'vite.config.mjs'] as $name) {
            $path = base_path($name);
            if (! $files->exists($path)) {
                continue;
            }
            $source = (string) $files->get($path);
            if (str_contains($source, self::ENTRY)) {
                return true;
            }
            $patched = preg_replace('/(input\s*:\s*\[)/', "$1\n                '".self::ENTRY."',", $source, 1, $count);
            if ($count === 1 && is_string($patched)) {
                $files->put($path, $patched);

                return true;
            }
        }

        return false;
    }

    private function pointConfigAtVite(Filesystem $files): bool
    {
        $path = config_path('admin.php');
        if (! $files->exists($path)) {
            return false;
        }
        $source = (string) $files->get($path);
        $source = preg_replace(
            ["/'vite_manifest'\s*=>\s*null,/", "/'vite_entry'\s*=>\s*null,/"],
            ["'vite_manifest' => public_path('build/manifest.json'),", "'vite_entry' => '".self::ENTRY."',"],
            $source,
        );
        if (! is_string($source)) {
            return false;
        }
        $files->put($path, $source);

        return str_contains($source, "'vite_entry' => '".self::ENTRY."'");
    }

    private function maybeAddComposerHook(Filesystem $files): void
    {
        $path = base_path('composer.json');
        if ($this->option('no-composer-hook') || ! $files->exists($path)) {
            return;
        }
        $composer = json_decode((string) $files->get($path), true);
        if (! is_array($composer)) {
            return;
        }
        $hooks = (array) ($composer['scripts']['post-update-cmd'] ?? []);
        $command = '@php artisan admin:publish --ansi';
        if (in_array($command, $hooks, true)) {
            return;
        }
        if (! $this->confirms('Republish the admin frontend after every `composer update`?', true)) {
            return;
        }
        $hooks[] = $command;
        $composer['scripts']['post-update-cmd'] = array_values($hooks);
        $files->put($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->components->task('composer.json post-update-cmd', fn (): bool => true);
    }

    /**
     * Points config('admin.auth') at the host's default guard and publishes
     * the users-table migration. The values come from config/auth.php, so a
     * renamed guard or a custom user model is picked up as is.
     */
    private function configureShared(Filesystem $files): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $provider = (string) config("auth.guards.{$guard}.provider", 'users');
        $model = ltrim((string) config("auth.providers.{$provider}.model", 'App\\Models\\User'), '\\');
        $broker = (string) config('auth.defaults.passwords', $provider);

        $this->components->task('config/admin.php: shared strategy', function () use ($files, $guard, $provider, $model, $broker): bool {
            $path = config_path('admin.php');
            if (! $files->exists($path)) {
                return false;
            }
            $source = str_replace(
                [
                    "env('ADMIN_AUTH_STRATEGY', 'dedicated')",
                    "env('ADMIN_GUARD', 'admin')",
                    "env('ADMIN_PROVIDER', 'admin_users')",
                    "'model' => Dskripchenko\\LaravelAdmin\\Models\\AdminUser::class,",
                    "'password_broker' => 'admin_users',",
                ],
                [
                    "env('ADMIN_AUTH_STRATEGY', 'shared')",
                    "env('ADMIN_GUARD', '{$guard}')",
                    "env('ADMIN_PROVIDER', '{$provider}')",
                    "'model' => \\{$model}::class,",
                    "'password_broker' => '{$broker}',",
                ],
                (string) $files->get($path),
            );
            $files->put($path, $source);

            return str_contains($source, "env('ADMIN_AUTH_STRATEGY', 'shared')");
        });

        // The rest of this run — the migration, the first administrator —
        // must already see the new values.
        config([
            'admin.auth.strategy' => 'shared',
            'admin.auth.guard' => $guard,
            'admin.auth.provider' => $provider,
            'admin.auth.model' => $model,
            'admin.auth.password_broker' => $broker,
        ]);
        app(\Dskripchenko\LaravelAdmin\Panel\PanelRegistry::class)->flush();

        $this->publish('admin-shared-migrations', 'Migration: admin columns on the users table');

        if (! $this->modelHasAdminAccess($model)) {
            $this->newLine();
            $this->line("  Add the admin traits to {$model}:");
            $this->line('    use \Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;');
            $this->line('    use \Dskripchenko\LaravelAdmin\Auth\Concerns\HasAdminTwoFactor;');
        }
    }

    private function modelHasAdminAccess(string $model): bool
    {
        return class_exists($model)
            && in_array(\Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess::class, class_uses_recursive($model), true);
    }

    /**
     * In the shared strategy the first administrator is usually someone who
     * already has an account; admin:user grants them the role, or creates a
     * new user when the email is unknown.
     */
    private function createSharedAdmin(): void
    {
        $model = (string) config('admin.auth.model');
        if (! $this->modelHasAdminAccess($model)) {
            $this->components->warn("No administrator yet: add the traits to {$model} first.");
            $this->line('  Then grant the role to an existing user: php artisan admin:user you@example.com --super');

            return;
        }

        $email = text('Email of the first administrator (an existing user, or a new one)', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) === false ? 'Not a valid email' : null);

        $this->call('admin:user', ['name' => $email, '--super' => true]);
    }

    private function createAdmin(): void
    {
        $name = text('Name', required: true);
        $email = text('Email', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) === false ? 'Not a valid email' : null);
        $secret = password('Password (at least 8 characters)', required: true, validate: fn (string $v) => strlen($v) < 8 ? 'Too short' : null);

        $this->call('admin:user', [
            'name' => $name,
            'email' => $email,
            'password' => $secret,
            '--super' => true,
        ]);
    }

    /** A yes/no question that answers `$default` when the command runs non-interactively. */
    private function confirms(string $question, bool $default): bool
    {
        return $this->input->isInteractive() ? confirm($question, default: $default) : $default;
    }
}
