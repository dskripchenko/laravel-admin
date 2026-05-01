<?php

declare(strict_types=1);

return [
    'default_options' => [
        'theme' => 'snow',
        'toolbar' => 'full',
        'placeholder' => 'Введите текст...',
    ],

    'image_upload' => [
        'endpoint' => '/api/admin/uploads',
        'disk' => 'public',
    ],
];
