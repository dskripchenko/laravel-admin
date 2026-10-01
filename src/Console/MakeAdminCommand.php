<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Hashing\Hasher;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

use Throwable;

/**
 * `php artisan admin:user [name] [email] [password]`
 *
 * Creates an administrator. Without arguments it runs interactively, through
 * Laravel Prompts.
 *
 * `--super` assigns the Super Admin system role, whose permissions are
 * `['*']`; it is created idempotently by the slug `super-admin`.
 */
final class MakeAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:user
                            {name? : The name}
                            {email? : Email}
                            {password? : The password}
                            {--super : Assign the Super Admin role}';

    /**
     * @var string
     */
    protected $description = 'Create an administrator';

    public function handle(Hasher $hasher): int
    {
        $modelClass = (string) config('admin.auth.model', \Dskripchenko\LaravelAdmin\Models\AdminUser::class);

        if (! class_exists($modelClass)) {
            $this->error("Class {$modelClass} not found. Check config('admin.auth.model').");

            return self::FAILURE;
        }

        $name = (string) ($this->argument('name') ?: text(label: 'Name', required: true));
        $email = (string) ($this->argument('email') ?: text(
            label: 'Email',
            required: true,
            validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) === false ? 'Not a valid email address' : null,
        ));
        $rawPassword = (string) ($this->argument('password') ?: password(
            label: 'Password',
            required: true,
            validate: fn (string $v) => strlen($v) < 8 ? 'At least 8 characters' : null,
        ));

        try {
            /** @var \Illuminate\Database\Eloquent\Model $admin */
            $admin = new $modelClass;
            $admin->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $hasher->make($rawPassword),
                'is_active' => true,
                // No locale of its own: the browser's or the application's
                // applies until the administrator picks one.
                'locale' => null,
                'theme' => (string) config('admin.ui.default_theme', 'light'),
            ])->save();
        } catch (Throwable $e) {
            $this->error('Could not create the administrator: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Administrator created: {$email} (id={$admin->getKey()})");

        if ($this->option('super')) {
            if (! method_exists($admin, 'assignRole')) {
                $this->error('The model does not use HasAdminAccess; no role assigned.');

                return self::FAILURE;
            }

            $role = \Dskripchenko\LaravelAdmin\Permission\Models\Role::query()->firstOrCreate(
                ['slug' => 'super-admin'],
                ['name' => 'Super Admin', 'permissions' => ['*'], 'is_system' => true],
            );
            $admin->assignRole($role);
            $this->info('Super Admin role assigned (permissions: *)');
        }

        return self::SUCCESS;
    }
}
