<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

/**
 * Минимальная Eloquent-модель для тестов Resource'а.
 *
 * @internal
 */
final class TestResourceUserModel extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
