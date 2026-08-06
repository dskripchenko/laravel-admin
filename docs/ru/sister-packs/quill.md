# dskripchenko/laravel-admin-quill

## 1. Назначение

Альтернативный WYSIWYG-движок поверх Quill для Field `Wysiwyg`. Главные причины предпочесть Quill вместо default Tiptap:

- устоявшаяся Quill-разметка в legacy-данных (HTML или Delta-формат);
- требование к **Delta-формату** вместо HTML — для последующего realtime co-editing через Quill-cursors / Yjs;
- более лёгкий бандл при минимальной toolbar.

**Use case:** редактор комментариев / коротких заметок, где не нужна полная мощь Tiptap; интеграция с co-editing-инструментами на Delta.

## 2. Состав

### Vue-компонент

`<QuillEditor>` — обёртка над `@vueup/vue-quill` с:

- режимами вывода `output('html')` (по умолчанию) или `output('delta')`;
- кастомным image-upload через core'овский `UploadController`;
- темизацией через CSS-переменные admin (light/dark);
- передачей `modules` как пропа (для подключения toolbar / clipboard / formula / video).

### Использование

```php
Field\Wysiwyg::make('body')
    ->driver('quill')
    ->output('delta')                      // 'html' | 'delta'
    ->modules(['toolbar' => [['bold', 'italic'], ['link']]]);
```

При `output('delta')` Field возвращает JSON с Quill Delta — пригоден для реалтайм-синхронизации, хранится в `JSON`-колонке.

## 3. Зависимости

**Composer:** `dskripchenko/laravel-admin: ^1.0`.

**NPM peer:**

```json
{
  "@vueup/vue-quill": "^1.2",
  "quill": "^2"
}
```

## 4. Миграции

Нет.

## 5. Permissions

Не регистрирует свои permissions.

## 6. Конфиг

`config/admin-quill.php`:

```php
return [
    'theme'  => 'snow',                     // 'snow' | 'bubble'
    'output' => 'html',                     // default — 'html' | 'delta'

    'default_modules' => [
        'toolbar' => [
            [['header' => [1, 2, 3, false]]],
            ['bold', 'italic', 'underline', 'strike'],
            [['list' => 'ordered'], ['list' => 'bullet']],
            ['link', 'image'],
            ['clean'],
        ],
    ],

    'image_upload' => [
        'endpoint' => '/api/admin/uploads',
        'disk'     => 'public',
    ],
];
```

## 7. Подключение

```bash
composer require dskripchenko/laravel-admin-quill
npm i @vueup/vue-quill quill
php artisan admin:plugin:install quill
```

## 8. Лицензия

Quill — **BSD-3-Clause**. Чистая лицензия, без коммерческих ограничений.

## 9. Зачем sister, а не core

- Quill — second-tier WYSIWYG для admin (Tiptap современнее, активнее развивается).
- Delta-формат не нужен большинству; для pure-HTML случаев Tiptap подходит лучше.
- Зависимость ~250KB на бандл — лишняя для проектов, не использующих Quill.
