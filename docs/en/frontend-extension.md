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
    titleGuard: { titleSuffix: ' — My Admin' },
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

Available: `useAuthStore`, `useManifestStore`, `useMenuStore`,
`useThemeStore`, `useLocaleStore`, `useNotificationsStore`,
`useResourceIndexStore`, `useResourceFormStore`, `useScreenStore`,
`useDashboardStore`, `useI18nStore`, `useNavigationStore`,
`useToastStore`.

## See also

- [Fields reference](fields-reference.md)
- [Layouts reference](layouts-reference.md)
- [Architecture](architecture.md)
