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
 * Создаёт администратора. Без аргументов — interactive (Laravel Prompts).
 *
 * Флаг --super на фазе P0 ничего пока не делает — RBAC появится в P2.
 * Зарезервирован для будущего: с ролями он будет назначать `Super Admin`.
 */
final class MakeAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:user
                            {name? : Имя}
                            {email? : Email}
                            {password? : Пароль}
                            {--super : Назначить роль Super Admin (P2+)}';

    /**
     * @var string
     */
    protected $description = 'Создать администратора';

    public function handle(Hasher $hasher): int
    {
        $modelClass = (string) config('admin.auth.model', \Dskripchenko\LaravelAdmin\Models\AdminUser::class);

        if (! class_exists($modelClass)) {
            $this->error("Класс {$modelClass} не найден. Проверьте config('admin.auth.model').");

            return self::FAILURE;
        }

        $name = (string) ($this->argument('name') ?: text(label: 'Имя', required: true));
        $email = (string) ($this->argument('email') ?: text(
            label: 'Email',
            required: true,
            validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) === false ? 'Невалидный email' : null,
        ));
        $rawPassword = (string) ($this->argument('password') ?: password(
            label: 'Пароль',
            required: true,
            validate: fn (string $v) => strlen($v) < 8 ? 'Минимум 8 символов' : null,
        ));

        try {
            /** @var \Illuminate\Database\Eloquent\Model $admin */
            $admin = new $modelClass;
            $admin->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $hasher->make($rawPassword),
                'is_active' => true,
                'locale' => (string) config('admin.ui.default_locale', 'ru'),
                'theme' => (string) config('admin.ui.default_theme', 'light'),
            ])->save();
        } catch (Throwable $e) {
            $this->error('Не удалось создать администратора: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Администратор создан: {$email} (id={$admin->getKey()})");

        if ($this->option('super')) {
            $this->warn('Опция --super пока не реализована (фаза P2). Роль Super Admin не назначена.');
        }

        return self::SUCCESS;
    }
}
