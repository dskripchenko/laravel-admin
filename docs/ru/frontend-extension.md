---
title: Расширение фронтенда
audience: developer
status: stable
locale: ru
translated_from: en/frontend-extension.md
translated_at: 2026-10-02
---

# Расширение фронтенда

SPA поставляется с готовыми реестрами полей, layout'ов, виджетов и
элементов infolist. Свои Vue-компоненты host-проект регистрирует в них —
форкать пакет не нужно.

## Монтирование

```js
import { createAdminApp } from '@dskripchenko/laravel-admin'
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__, {
  pages: {
    /* override default pages */
    home: MyHome,
    forbidden: My403,
  },
  router: {
    extraRoutes: [{ path: '/integrations', component: IntegrationsPage }],
    titleGuard: { template: '{title} — My Admin' },
  },
  onAppCreated(app) {
    /* app.use(MyPlugin) */
  },
})

app.mount('#admin-app')
```

`pages` заменяет стандартные страницы (`login`, `home`, `forbidden`,
`notFound`, `profile`, `dashboard`, `settings`, `screen`, страницы
ресурса и т. д.), `router.extraRoutes` добавляет свои маршруты, а
`router.titleGuard` задаёт шаблон заголовка вкладки: `{title}` — заголовок
маршрута, `{brand}` — название бренда. `createAdminApp` возвращает
`{ app, router, client }`.

## Своё поле

```ts
// resources/js/admin/MyColorField.vue
<script setup lang="ts">
import { computed } from 'vue'
import { UidFormField } from '@dskripchenko/ui'
import { useFormState } from '@dskripchenko/laravel-admin'

interface Props { name: string; label?: string; required?: boolean }
const props = defineProps<Props>()
const form = useFormState()

const value = computed<string>(() => (form.getField(props.name) as string) ?? '#000000')
</script>

<template>
  <UidFormField :label="label" :required="required">
    <input
      type="color"
      :value="value"
      @input="form.setField(name, ($event.target as HTMLInputElement).value)"
    />
  </UidFormField>
</template>
```

```ts
// resources/js/admin.js
import { createAdminApp, registerField } from '@dskripchenko/laravel-admin'
import MyColorField from './admin/MyColorField.vue'

registerField('color-picker', MyColorField)

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
app.mount('#admin-app')
```

На бэкенде:

```php
class ColorPicker extends Field
{
    public function fieldType(): string { return 'color-picker'; }
}
```

Компонент выбирается по `type` из JSON поля — то есть по значению
`fieldType()`. Остальные свойства узла приходят в компонент как props.

## Свой layout

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import HeroBlock from './admin/HeroBlock.vue'

registerLayout('hero', HeroBlock)
```

```php
Layout::view('hero', ['headline' => 'Welcome'])
```

`HeroBlock.vue` получает `headline` как prop. Если имя не
зарегистрировано, вместо компонента выводится предупреждение с подсказкой,
что именно зарегистрировать.

## Свой виджет

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './widgets/WeatherWidget.vue'

registerWidget('weather', WeatherWidget)
```

`WeatherWidget.vue` получает весь узел виджета; ключи из `data`
дополнительно разворачиваются на верхний уровень props:

```vue
<script setup lang="ts">
interface Props {
  title?: string
  /* fields from data */
  temp?: number
  city?: string
}
defineProps<Props>()
</script>
```

### Добавление пользователем

Тип, который пользователь может добавить на дашборд (**Редактировать →
Добавить виджет**), описывается для диалога добавления и настройки: подпись,
иконка, размер по умолчанию и форма настроек. Описание передаётся третьим
аргументом `registerWidget()`, а тип, зарегистрированный в другом месте,
описывается через `describeWidget()`:

```ts
import { registerWidget, describeWidget } from '@dskripchenko/laravel-admin'
import { CloudSun } from 'lucide-vue-next'

registerWidget('weather', WeatherWidget, {
  label: 'Погода',               // строка переводится; () => string показывается как есть
  icon: CloudSun,
  defaultSize: 3,                // колонок из 12; без него — 6
  defaultRowSpan: 1,             // строк; когда ни виджет, ни пользователь не задали высоту
  fields: [
    { kind: 'text', key: 'city', label: 'Город', required: true },
    { kind: 'select', key: 'units', label: 'Единицы', default: 'metric',
      options: [{ value: 'metric', label: '°C' }, { value: 'imperial', label: '°F' }] },
    { kind: 'number', key: 'days', label: 'Дней', default: 3 },
    { kind: 'switch', key: 'alerts', label: 'Показывать предупреждения' },
  ],
})

describeWidget('heatmap', { addable: false })   // убрать тип из диалога добавления
```

Диалог сохраняет форму как `config` виджета в раскладке пользователя (здесь
`{ title, city, units, days, alerts }`), а компонент виджета получает его как
свои данные, развёрнутые в props, — так же, как `data()` серверного виджета.

Виды полей: `text`, `textarea`, `code` (моноширинное поле; `rows`), `number`
(`default`, `min`, `max`; сохраняется числом), `select` (`options` — список
или `(context) => options`, вычисленный по панели) и `switch` (сохраняется
булевым). У каждого поля есть `label`, `help`, `placeholder`, `required`
(диалог ждёт значения) и `visible(values)` — поле показывается и сохраняется,
только пока функция возвращает true. `context.resources` — ресурсы манифеста,
которые пользователь может смотреть, `context.hasPermission(key)` проверяет
право:

```ts
{ kind: 'select', key: 'resource', label: 'Ресурс', required: true,
  options: (ctx) => ctx.resources.map((r) => ({ value: r.slug, label: () => r.label })) }
```

Если сохраняемая форма отличается от формы полей, `read(config)` превращает
сохранённый config в значения формы, а `build(values, { type, title })` —
обратно (заголовок — поле самого диалога, он всегда сохраняется как
`config.title`). Форму, которую поля не описывают, задают своим компонентом в
`form`: он получает `modelValue` (значения), `type` и `context` и эмитит
`update:modelValue`.

Компонент без описания по-прежнему предлагается под именем типа — с одними
заголовком и размером. Встроенные типы описаны так же
(`builtinWidgetDescriptions()`); описание, заданное до запуска приложения,
заменяет встроенное.

## Свой элемент infolist

```ts
import { registerInfolistEntry } from '@dskripchenko/laravel-admin'
import StatusEntry from './entries/StatusEntry.vue'

registerInfolistEntry('status', StatusEntry)
```

```php
class StatusEntry extends Entry
{
    public function entryType(): string { return 'status'; }
}
```

## Наборы компонентов

Несколько компонентов можно зарегистрировать разом:

```ts
import { registerComponents } from '@dskripchenko/laravel-admin'

registerComponents({
  fields: { 'color-picker': MyColorField, 'rich-tags': MyRichTags },
  layouts: { 'hero': HeroBlock, 'banner': MyBanner },
})
```

Для виджетов и элементов infolist есть свои пакетные функции —
`registerWidgets()` и `registerInfolistEntries()`.

## Состояние формы из своего компонента

```ts
import { useFormState, tryUseFormState } from '@dskripchenko/laravel-admin'

const form = useFormState()                  // throws if outside form
const formOpt = tryUseFormState()            // returns null if absent

form.getField('title')
form.setField('title', 'New value')
form.setError('title', ['Too short'])
form.errors.title                            // current errors
```

`useFormState()` бросает исключение, если компонент находится вне формы;
`tryUseFormState()` в этом случае возвращает `null`.

## API-клиент (axios)

```ts
import { getAdminClient } from '@dskripchenko/laravel-admin'

const client = getAdminClient()
const result = await client.get('/system/menu')   // unwraps {success, payload}
const article = await client.post('/articles/create', { title: 'Hi' })
```

Клиент сам разворачивает конверт `{success, payload}` и возвращает
`payload`. Исходный экземпляр axios доступен как `client.raw`.

Ошибки приходят типизированными подклассами `ApiError`:

```ts
import { ApiError, ValidationError, ForbiddenError } from '@dskripchenko/laravel-admin'

try {
  await client.post('/articles/create', { /* invalid */ })
} catch (e) {
  if (e instanceof ValidationError) {
    e.fields              // { title: ['required'] }
  } else if (e instanceof ForbiddenError) {
    /* 403 */
  }
}
```

Кроме них есть `UnauthenticatedError`, `NotFoundError` и `NetworkError`.

## Сторы (Pinia)

```ts
import { useAuthStore, useManifestStore, useNotificationsStore } from '@dskripchenko/laravel-admin'
```

Из корня пакета экспортируются: `useAuthStore`, `useManifestStore`,
`useMenuStore`, `useThemeStore`, `useLocaleStore`, `useNotificationsStore`,
`useResourceIndexStore`, `useResourceFormStore`, `useI18nStore`. Сторы
экранов, дашбордов и навигации — внутренние для страниц SPA и в публичный
API не входят.

## См. также

- [Каталог полей](fields-reference.md)
- [Каталог layout'ов](layouts-reference.md)
- [Архитектура](architecture.md)
