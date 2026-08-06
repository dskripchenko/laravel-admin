# Как участвовать

Спасибо, что решили поучаствовать в `dskripchenko/laravel-admin`. Здесь —
порядок работы, стиль кода и то, чего ждут на ревью.

> 🌐 [English](../../.github/CONTRIBUTING.md) · [Deutsch](../de/contributing.md) · **Русский** · [中文](../zh/contributing.md)

## Витрина компонентов

```bash
npm run storybook        # http://localhost:6007
npm run storybook:build  # то же статикой — именно это проверяет CI
```

Витрина показывает экран на любой ширине без поднятого бэкенда и без
данных — так вскрываются дефекты раскладки, которых не видно на
разработческом мониторе. Ширины в переключателе те же, что проверяет CI
printable (телефон 402, планшет 768, рабочий стол 1280): дефект, найденный
здесь, воспроизводится тестом и наоборот.

Истории лежат рядом с компонентами — `*.stories.ts`.

## Быстрый старт (локальная разработка)

```bash
git clone https://github.com/dskripchenko/laravel-admin.git
cd laravel-admin
composer install
npm install
```

Сборка и проверки:

```bash
npm run build              # продакшен-сборка фронтенда
vendor/bin/pest            # тесты бэкенда (801+)
npm test                   # тесты фронтенда (319+)
npx vue-tsc --noEmit       # проверка типов
vendor/bin/pint            # стиль PHP (правит сам)
vendor/bin/phpstan analyse # статический анализ (уровень 5)
```

## Структура репозитория

| Каталог | Что внутри |
|---|---|
| `src/` | Исходники PHP (Resource, Screen, Field, Layout, Action, Widget, Menu и прочее) |
| `resources/ts/` | SPA на Vue 3 и TypeScript |
| `resources/views/` | Blade-шаблон оболочки |
| `config/` | Конфиг по умолчанию (`admin.php`) |
| `database/migrations/` | Встроенные миграции |
| `routes/` | Маршруты админки (регистрируются через `AdminServiceProvider`) |
| `tests/` | Тесты Pest (Feature + Unit + Fixtures) |
| `docs/` | Это дерево документации (`{en,ru,de,zh}/`) |

## Ветки и коммиты

- Ветвитесь от `main`.
- Одна тема на pull request. Держите их небольшими.
- Сообщение коммита — в повелительном наклонении, с областью изменения:
  ```
  feat(dashboard): widget polling по Widget::refresh
  fix(notifications): graceful fallback if table missing
  docs(menu): add MenuNode::dashboard examples
  test(screen): cover runMethod 422 path
  refactor(widget): inline rowSpan resolver
  chore: bump @dskripchenko/wysiwyg ^0.2.7
  ```
- Трейлер `Co-Authored-By` для работы с ИИ-помощником приветствуется.

## Стиль кода

### PHP

- **PHP 8.5+**, `strict_types` объявляется в начале каждого файла.
- Форматирует **Pint** (`vendor/bin/pint`). Запускайте перед коммитом.
- **PHPStan, уровень 5.** Не расширяйте типы и не глушите `@phpstan-ignore` —
  чините причину (или объясните, почему проблема досталась по наследству).
- Импорты типов через `use`, а не FQCN по месту.
- Докблоки `@param`/`@return` у публичных методов — только там, где типов не
  хватает: обобщённые массивы, формы callable и тому подобное.

### TypeScript и Vue

- Везде `<script setup lang="ts">`. Никакого Options API.
- Composable'ы — в `composables/` (camelCase, префикс `use*`).
- Пропсы через `withDefaults(defineProps<Props>(), { ... })`.
- Имена классов в духе БЭМ: `.admin-{component}__{element}--{modifier}`.
- Примитивы фронтенд берёт из `@dskripchenko/ui` — не пишите свою вёрстку,
  используйте `UidButton`, `UidInput` и остальные.

### CSS

- Только CSS-переменные (`var(--uid-...)`). Ни Tailwind, ни SCSS, ни CSS-in-JS.
- Никаких `<style scoped>` — темам нужно пробиваться внутрь.

## Тесты

- **Бэкенд**: Pest (`vendor/bin/pest`). Структура `src/` зеркалится в
  `tests/Feature/` и `tests/Unit/`. Фикстуры — в `tests/Fixtures/`
  (подключаются classmap'ом composer'а, глобальное пространство имён).
- **Фронтенд**: Vitest + jsdom + `@vue/test-utils`. Файл теста лежит рядом с
  проверяемым (`Component.test.ts`).
- Базу не мокайте — берите SQLite в памяти, она уже настроена
  `Orchestra\Testbench`.
- Сквозной смоук на Playwright живёт в `demo/e2e-full-flow.mjs`.

## Релиз

Пакет уезжает в два реестра из одного тега: Composer читает сам тег, npm
читает `package.json`. Расходиться им можно — изменение, которое затронуло
только PHP, получает тег без релиза в npm.

1. Обновите `CHANGELOG.md`.
2. Если менялся фронтенд — тем же коммитом поднимите `version` в
   `package.json`. Если не менялся, не трогайте.
3. Поставьте тег `vX.Y.Z` и отправьте его.

Отправка тега запускает `Publish to npm`. Публикация происходит, только если
`package.json` называет ровно версию тега и этой версии ещё нет в реестре. Всё
прочее — тег только под Composer, повторный запуск на уже изданной версии —
завершается зелёным без публикации, чтобы история не врала о том, что
произошло на самом деле.

Перед публикацией гоняются `typecheck` и `test`: тег может стоять на коммите,
который CI по веткам не видел, а неудачную версию из npm уже не вынуть.

Если тег ушёл раньше, чем догнали версию, — поднимите её в `main` и запустите
`Publish to npm` вручную (`workflow_dispatch`), проверки те же.

## Пакеты-спутники

Этот репозиторий — **ядро**. Спутники (`starter`, `health`, `jobs`, `media`,
`pulse`, `search`, `quill`, `tinymce`) живут отдельными репозиториями. Они
зависят от этого пакета через composer; версионный контракт — `^1.x` до
слома API.

## Сообщения об ошибках

Через issues на GitHub. Приложите:
- версии Laravel, PHP и пакета;
- минимальное воспроизведение (фрагмент кода или ссылку на репозиторий);
- чего вы ждали и что получилось;
- ошибки в консоли и трассировку, если есть.

## Безопасность

Об уязвимостях пишите на `denskrp90@gmail.com`, а не в публичные issues.

## Лицензия

Участвуя, вы соглашаетесь, что ваша работа выходит под
[лицензией MIT](../../LICENSE) проекта.
