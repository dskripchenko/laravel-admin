---
title: Frontend Extension
audience: developer
status: stable
locale: en
---

# Frontend Extension

The SPA bundle ships with default field/layout/widget/infolist registries.
Host projects can register custom Vue components without forking.

## Mount

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

## Custom field

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

Backend:

```php
class ColorPicker extends Field
{
    public function fieldType(): string { return 'color-picker'; }
}
```

## Custom layout

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import HeroBlock from './admin/HeroBlock.vue'

registerLayout('hero', HeroBlock)
```

```php
Layout::view('hero', ['headline' => 'Welcome'])
```

`HeroBlock.vue` receives `headline` as a prop.

## Custom widget

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './widgets/WeatherWidget.vue'

registerWidget('weather', WeatherWidget)
```

`WeatherWidget.vue` receives the entire widget node (after data spread):

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

### Letting users add it

A type a user may add on the dashboard (**Edit → Add widget**) is described for
the add and configure dialog — its label, icon, default size and config form.
Pass the description as `registerWidget()`'s third argument, or describe a type
registered elsewhere with `describeWidget()`:

```ts
import { registerWidget, describeWidget } from '@dskripchenko/laravel-admin'
import { CloudSun } from 'lucide-vue-next'

registerWidget('weather', WeatherWidget, {
  label: 'Weather',              // a string is translated; () => string is shown as is
  icon: CloudSun,
  defaultSize: 3,                // columns of 12; 6 when unset
  defaultRowSpan: 1,             // rows; used when neither the widget nor the user set one
  fields: [
    { kind: 'text', key: 'city', label: 'City', required: true },
    { kind: 'select', key: 'units', label: 'Units', default: 'metric',
      options: [{ value: 'metric', label: '°C' }, { value: 'imperial', label: '°F' }] },
    { kind: 'number', key: 'days', label: 'Days', default: 3 },
    { kind: 'switch', key: 'alerts', label: 'Show alerts' },
  ],
})

describeWidget('heatmap', { addable: false })   // keep a type out of the add dialog
```

The dialog saves the form as the widget's `config` in the user's layout
(`{ title, city, units, days, alerts }` here), and the widget component gets it
as its data, flattened into props — the same as a backend widget's `data()`.

Field kinds: `text`, `textarea`, `code` (a monospace textarea; `rows`),
`number` (`default`, `min`, `max`; saved as a number), `select` (`options` — a
list, or `(context) => options` computed from the panel) and `switch` (saved as
a boolean). Every field takes `label`, `help`, `placeholder`, `required` (the
dialog waits for a value) and `visible(values)` — a field shown, and saved, only
while it returns true. `context.resources` holds the manifest's resources the
user may view, and `context.hasPermission(key)` checks a permission:

```ts
{ kind: 'select', key: 'resource', label: 'Resource', required: true,
  options: (ctx) => ctx.resources.map((r) => ({ value: r.slug, label: () => r.label })) }
```

When the saved shape differs from the form, `read(config)` turns a saved config
into the form's values and `build(values, { type, title })` turns them back
(the title is the dialog's own and always saved as `config.title`). A form the
fields cannot express is a component of your own in `form`: it gets
`modelValue` (the values), `type` and `context` and emits `update:modelValue`.

A component registered without a description is still offered under its type
name, with only the title and the size to set. The built-in types are described
the same way (`builtinWidgetDescriptions()`); a description given before the
app starts replaces the built-in one.

## Custom infolist entry

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

## Bundles

Register many at once:

```ts
import { registerComponents } from '@dskripchenko/laravel-admin'

registerComponents({
  fields: { 'color-picker': MyColorField, 'rich-tags': MyRichTags },
  layouts: { 'hero': HeroBlock, 'banner': MyBanner },
})
```

## Form state from a custom component

```ts
import { useFormState, tryUseFormState } from '@dskripchenko/laravel-admin'

const form = useFormState()                  // throws if outside form
const formOpt = tryUseFormState()            // returns null if absent

form.getField('title')
form.setField('title', 'New value')
form.setError('title', ['Too short'])
form.errors.title                            // current errors
```

## API client (axios)

```ts
import { getAdminClient } from '@dskripchenko/laravel-admin'

const client = getAdminClient()
const result = await client.get('/system/menu')   // unwraps {success, payload}
const article = await client.post('/articles/create', { title: 'Hi' })
```

Errors are thrown as typed `ApiError` subclasses:

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

## Stores (Pinia)

```ts
import { useAuthStore, useManifestStore, useNotificationsStore } from '@dskripchenko/laravel-admin'
```

Exported from the package root: `useAuthStore`, `useManifestStore`,
`useMenuStore`, `useThemeStore`, `useLocaleStore`, `useNotificationsStore`,
`useResourceIndexStore`, `useResourceFormStore`, `useI18nStore`. The
screen, dashboard and navigation stores are internal to the SPA's pages and
are not part of the public API.

## See also

- [Fields reference](fields-reference.md)
- [Layouts reference](layouts-reference.md)
- [Architecture](architecture.md)
