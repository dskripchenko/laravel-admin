<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

/**
 * Выключена ли учётная запись.
 *
 * Признак выключения зависит от модели: у `AdminUser` это `is_active`,
 * у панельных user-моделей — `enabled`. Проверка живёт в одном месте,
 * потому что применяется дважды и обязана давать одинаковый ответ: на входе
 * (AuthController) и на каждом запросе (AdminAuth). Разойдись они — панель
 * пускала бы уволенного внутрь и выкидывала его только следующим запросом.
 */
final class AccountState
{
    /** @var list<string> */
    private const FLAGS = ['is_active', 'enabled'];

    public static function isDisabled(object $user): bool
    {
        if (! method_exists($user, 'getAttribute')) {
            return false;
        }

        foreach (self::FLAGS as $flag) {
            $value = $user->getAttribute($flag);

            // Отсутствие поля — не запрет: у модели может не быть выключателя
            // вовсе. Запрет — только явно ложное значение, в том числе `0` из
            // баз без булева типа.
            if ($value !== null && ! $value) {
                return true;
            }
        }

        return false;
    }
}
