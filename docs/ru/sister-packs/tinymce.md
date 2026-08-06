# dskripchenko/laravel-admin-tinymce

## 1. Назначение

Альтернативный WYSIWYG-движок поверх TinyMCE для Field `Wysiwyg`. Tiptap (default в core) не всем подходит: legacy-контент с TinyMCE-разметкой, требования к word-paste-cleanup, специфические TinyMCE-плагины.

**Use case:** миграция с старой админки на TinyMCE, требование команды контента к привычному набору toolbar-кнопок и плагинам Tiny (advlist, advtable, paste, lists и т.д.).

## 2. Состав

### Vue-компонент

`<TinymceEditor>` — обёртка над `@tinymce/tinymce-vue` с:

- двусторонним bind через `v-model`;
- передачей конфига как пропа `init` (с сохранением default'ов из `config/admin-tinymce.php`);
- кастомным image-upload через `images_upload_handler` → core'овский `UploadController`;
- интеграцией с темой admin (light/dark — переключает `skin`/`content_css`).

### Регистрация driver'а

После установки plugin регистрирует TinyMCE как доступный движок:

```php
// в AdminServiceProvider
Admin::plugin(AdminTinymcePlugin::class);
```

Использование:

```php
// Глобально как default
Admin::setWysiwygDriver('tinymce');

// Per-field
Field\Wysiwyg::make('body')->driver('tinymce')->plugins(['advlist', 'lists']);
```

## 3. Зависимости

**Composer:** `dskripchenko/laravel-admin: ^1.0`.

**NPM peer:**

```json
{
  "@tinymce/tinymce-vue": "^5",
  "tinymce": "^7"
}
```

## 4. Миграции

Нет.

## 5. Permissions

Не регистрирует свои permissions.

## 6. Конфиг

`config/admin-tinymce.php`:

```php
return [
    'license_key' => env('TINYMCE_LICENSE_KEY'),       // null = self-hosted
    'cdn_url'     => null,                              // null = bundle through Vite

    'default_init' => [
        'height'      => 400,
        'menubar'     => false,
        'plugins'     => 'advlist autolink lists link image charmap preview anchor pagebreak searchreplace wordcount visualblocks code fullscreen insertdatetime media table emoticons help paste',
        'toolbar'     => 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code preview fullscreen',
        'language'    => 'ru',
        'paste_data_images' => true,
    ],

    'image_upload' => [
        'endpoint' => '/api/admin/uploads',          // дефолтный UploadController
        'disk'     => 'public',
    ],
];
```

## 7. Подключение

```bash
composer require dskripchenko/laravel-admin-tinymce
npm i @tinymce/tinymce-vue tinymce
php artisan admin:plugin:install tinymce
```

Команда `admin:plugin:install tinymce`:

1. публикует config;
2. инжектит `import 'tinymce/tinymce'` + базовые плагины в `resources/ts/plugins.ts` (если папка существует) либо подсказывает как добавить руками.

## 8. ⚠️ Лицензия

TinyMCE распространяется по двум лицензиям: **GPL-2.0+** и **коммерческая (Tiny Cloud)**. В README пакета явное предупреждение и ссылка на [tiny.cloud/get-tiny](https://www.tiny.cloud/get-tiny/) — для проприетарных продуктов или premium-плагинов нужна коммерческая лицензия. admin сам не несёт лицензионной нагрузки, но обязан напоминать пользователю.

## 9. Зачем sister, а не core

- Дополнительная npm-зависимость ~600KB (TinyMCE + плагины).
- Лицензионная неопределённость (GPL vs commercial).
- Tiptap покрывает 90% потребностей; TinyMCE — это явный осознанный выбор.

Размещение в core обязало бы всех тащить TinyMCE даже тех, кому он не нужен.
