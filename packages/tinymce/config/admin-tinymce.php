<?php

declare(strict_types=1);

return [
    'license_key' => env('TINYMCE_LICENSE_KEY'),
    'cdn_url' => null,

    'default_init' => [
        'height' => 400,
        'menubar' => false,
        'plugins' => 'advlist autolink lists link image charmap preview anchor pagebreak searchreplace wordcount visualblocks code fullscreen insertdatetime media table emoticons help paste',
        'toolbar' => 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code preview fullscreen',
        'language' => 'ru',
        'paste_data_images' => true,
    ],

    'image_upload' => [
        'endpoint' => '/api/admin/uploads',
        'disk' => 'public',
    ],
];
