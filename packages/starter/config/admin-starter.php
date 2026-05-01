<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Какие Resource'ы регистрировать
    |--------------------------------------------------------------------------
    | Каждый ключ — toggle. Полный список реализован в Plugin'е; ключи в этом
    | массиве используются как gate. По умолчанию core-набор (users / roles /
    | audit_log) на. Optional resources (translations / content_blocks /
    | sessions) v0.1 не реализованы — будут добавлены позже.
    */

    'resources' => [
        'users' => true,
        'roles' => true,
        'audit_log' => true,
        'settings' => false, // not implemented in v0.1
        'translations' => false,
        'content_blocks' => false,
        'sessions' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Меню-группа
    |--------------------------------------------------------------------------
    | Resource'ы группируются под этой меткой в sidebar'е (через
    | Resource::$group). Иконку и порядок core читает из Resource::$icon +
    | Resource::menuOrder().
    */

    'menu_group' => 'Системные',
];
