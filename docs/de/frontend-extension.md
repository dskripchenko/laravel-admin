---
title: Frontend-Erweiterung
audience: developer
status: stable
locale: de
translated_from: en/frontend-extension.md
translated_at: 2026-10-02
---

# Frontend-Erweiterung

Das SPA-Bundle wird mit Standard-Registries für Felder, Layouts, Widgets und Infolists ausgeliefert.
Host-Projekte können eigene Vue-Komponenten registrieren, ohne das Paket zu forken.

## Einbinden

```js
import { createAdminApp } from '@dskripchenko/laravel-admin'
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__, {
  pages: {
    /* Standardseiten überschreiben */
    home: MyHome,
    forbidden: My403,
  },
  router: {
    extraRoutes: [{ path: '/integrations', component: IntegrationsPage }],
    titleGuard: { template: '{title} — Mein Admin' },
  },
  onAppCreated(app) {
    /* app.use(MyPlugin) */
  },
})

app.mount('#admin-app')
```

## Eigenes Feld

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

## Eigenes Layout

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import HeroBlock from './admin/HeroBlock.vue'

registerLayout('hero', HeroBlock)
```

```php
Layout::view('hero', ['headline' => 'Willkommen'])
```

`HeroBlock.vue` erhält `headline` als Prop.

## Eigenes Widget

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './widgets/WeatherWidget.vue'

registerWidget('weather', WeatherWidget)
```

`WeatherWidget.vue` erhält den gesamten Widget-Knoten (nach dem Ausbreiten von data):

```vue
<script setup lang="ts">
interface Props {
  title?: string
  /* Felder aus data */
  temp?: number
  city?: string
}
defineProps<Props>()
</script>
```

## Eigener Infolist-Eintrag

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

Mehrere auf einmal registrieren:

```ts
import { registerComponents } from '@dskripchenko/laravel-admin'

registerComponents({
  fields: { 'color-picker': MyColorField, 'rich-tags': MyRichTags },
  layouts: { 'hero': HeroBlock, 'banner': MyBanner },
})
```

## Formular-State aus einer eigenen Komponente

```ts
import { useFormState, tryUseFormState } from '@dskripchenko/laravel-admin'

const form = useFormState()                  // wirft außerhalb eines Formulars
const formOpt = tryUseFormState()            // liefert null, falls nicht vorhanden

form.getField('title')
form.setField('title', 'Neuer Wert')
form.setError('title', ['Zu kurz'])
form.errors.title                            // aktuelle Fehler
```

## API-Client (axios)

```ts
import { getAdminClient } from '@dskripchenko/laravel-admin'

const client = getAdminClient()
const result = await client.get('/system/menu')   // entpackt {success, payload}
const article = await client.post('/articles/create', { title: 'Hallo' })
```

Fehler werden als typisierte Unterklassen von `ApiError` geworfen:

```ts
import { ApiError, ValidationError, ForbiddenError } from '@dskripchenko/laravel-admin'

try {
  await client.post('/articles/create', { /* ungültig */ })
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

Aus dem Paket-Root exportiert: `useAuthStore`, `useManifestStore`,
`useMenuStore`, `useThemeStore`, `useLocaleStore`, `useNotificationsStore`,
`useResourceIndexStore`, `useResourceFormStore`, `useI18nStore`. Die
Stores für Screens, Dashboards und Navigation sind intern in den Seiten der SPA und
gehören nicht zur öffentlichen API.

## Siehe auch

- [Feld-Referenz](fields-reference.md)
- [Layout-Referenz](layouts-reference.md)
- [Architektur](architecture.md)
