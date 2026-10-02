<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

use Throwable;

/**
 * `php artisan admin:user [name] [email] [password]`
 * `php artisan admin:user alice@example.com --super`
 *
 * Creates an administrator. Without arguments it runs interactively, through
 * Laravel Prompts.
 *
 * `--super` assigns the Super Admin system role, whose permissions are
 * `['*']`; it is created idempotently by the slug `super-admin`.
 *
 * When a user with that email already exists — the usual case in the shared
 * strategy, where the administrators are the site's own users — nothing is
 * created: `--super` grants the role to the existing user, and the password
 * is not asked for; without `--super` the command fails.
 */
final class MakeAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:user
                            {name? : Name}
                            {email? : Email}
                            {password? : Password (at least 8 characters)}
                            {--super : Grant the Super Admin role (every permission)}';

    /**
     * @var string
     */
    protected $description = 'Create an administrator, or grant Super Admin to an existing user';

    public function handle(Hasher $hasher): int
    {
        $modelClass = (string) config('admin.auth.model', \Dskripchenko\LaravelAdmin\Models\AdminUser::class);

        if (! is_subclass_of($modelClass, Model::class)) {
            $this->error("Class {$modelClass} is not an Eloquent model. Check config('admin.auth.model').");

            return self::FAILURE;
        }

        // `admin:user alice@example.com --super` — an email alone, for
        // granting the role to someone who already exists.
        $nameArgument = (string) $this->argument('name');
        $emailArgument = (string) $this->argument('email');
        if ($emailArgument === '' && filter_var($nameArgument, FILTER_VALIDATE_EMAIL) !== false) {
            [$emailArgument, $nameArgument] = [$nameArgument, ''];
        }

        $email = (string) ($emailArgument ?: text(
            label: 'Email',
            required: true,
            validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) === false ? 'Not a valid email' : null,
        ));

        /** @var Model|null $existing */
        $existing = $modelClass::query()->where('email', $email)->first();

        if ($existing !== null) {
            $this->info("User {$email} already exists (id={$existing->getKey()}).");
            if (! $this->option('super')) {
                $this->error('Not created. Pass --super to grant the existing user the Super Admin role.');

                return self::FAILURE;
            }

            return $this->grantSuper($existing);
        }

        $name = $nameArgument !== '' ? $nameArgument : text(label: 'Name', required: true);
        $rawPassword = (string) ($this->argument('password') ?: password(
            label: 'Password',
            required: true,
            validate: fn (string $v) => strlen($v) < 8 ? 'At least 8 characters' : null,
        ));

        try {
            /** @var Model $admin */
            $admin = new $modelClass;
            $admin->forceFill($this->onlyExistingColumns($admin, [
                'name' => $name,
                'email' => $email,
                'password' => $hasher->make($rawPassword),
                'is_active' => true,
                // No locale of its own: the browser's or the application's
                // applies until the administrator picks one.
                'locale' => null,
                'theme' => (string) config('admin.ui.default_theme', 'light'),
            ]))->save();
        } catch (Throwable $e) {
            $this->error('Could not create the administrator: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Administrator created: {$email} (id={$admin->getKey()})");

        return $this->option('super') ? $this->grantSuper($admin) : self::SUCCESS;
    }

    private function grantSuper(Model $admin): int
    {
        if (! method_exists($admin, 'assignRole')) {
            $this->error('The model does not use the HasAdminAccess trait, so no role was assigned.');

            return self::FAILURE;
        }

        $role = \Dskripchenko\LaravelAdmin\Permission\Models\Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'permissions' => ['*'], 'is_system' => true],
        );
        $admin->assignRole($role);
        $this->info('Granted the Super Admin role (permissions: *).');

        return self::SUCCESS;
    }

    /**
     * The host's own users table in the shared strategy may lack the admin's
     * columns (is_active, locale, theme); the required ones are always kept,
     * so a missing name or email still fails loudly.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function onlyExistingColumns(Model $model, array $attributes): array
    {
        $columns = Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());
        if ($columns === []) {
            return $attributes;
        }

        $required = ['name', 'email', 'password'];

        return array_filter(
            $attributes,
            static fn (string $key): bool => in_array($key, $required, true) || in_array($key, $columns, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
