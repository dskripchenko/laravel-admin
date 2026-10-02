---
title: 前端扩展
audience: developer
status: stable
locale: zh
translated_from: en/frontend-extension.md
translated_at: 2026-10-02
---

# 前端扩展

SPA 包自带默认的字段/布局/小部件/infolist 注册表。宿主项目无需 fork 即可注册自定义
Vue 组件。

## 挂载

```js
import { createAdminApp } from '@dskripchenko/laravel-admin'
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__, {
  pages: {
    /* 覆盖默认页面 */
    home: MyHome,
    forbidden: My403,
  },
  router: {
    extraRoutes: [{ path: '/integrations', component: IntegrationsPage }],
    titleGuard: { template: '{title} — 我的管理后台' },
  },
  onAppCreated(app) {
    /* app.use(MyPlugin) */
  },
})

app.mount('#admin-app')
```

## 自定义字段

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

后端：

```php
class ColorPicker extends Field
{
    public function fieldType(): string { return 'color-picker'; }
}
```

## 自定义布局

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import HeroBlock from './admin/HeroBlock.vue'

registerLayout('hero', HeroBlock)
```

```php
Layout::view('hero', ['headline' => '欢迎'])
```

`HeroBlock.vue` 以 prop 的形式接收 `headline`。

## 自定义小部件

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './widgets/WeatherWidget.vue'

registerWidget('weather', WeatherWidget)
```

`WeatherWidget.vue` 接收整个小部件节点（data 展开之后）：

```vue
<script setup lang="ts">
interface Props {
  title?: string
  /* 来自 data 的字段 */
  temp?: number
  city?: string
}
defineProps<Props>()
</script>
```

## 自定义 infolist 条目

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

## 批量注册

一次注册多个：

```ts
import { registerComponents } from '@dskripchenko/laravel-admin'

registerComponents({
  fields: { 'color-picker': MyColorField, 'rich-tags': MyRichTags },
  layouts: { 'hero': HeroBlock, 'banner': MyBanner },
})
```

## 在自定义组件中访问表单状态

```ts
import { useFormState, tryUseFormState } from '@dskripchenko/laravel-admin'

const form = useFormState()                  // 在表单外调用时抛出异常
const formOpt = tryUseFormState()            // 不存在时返回 null

form.getField('title')
form.setField('title', '新值')
form.setError('title', ['太短'])
form.errors.title                            // 当前错误
```

## API 客户端（axios）

```ts
import { getAdminClient } from '@dskripchenko/laravel-admin'

const client = getAdminClient()
const result = await client.get('/system/menu')   // 解包 {success, payload}
const article = await client.post('/articles/create', { title: '你好' })
```

错误以带类型的 `ApiError` 子类抛出：

```ts
import { ApiError, ValidationError, ForbiddenError } from '@dskripchenko/laravel-admin'

try {
  await client.post('/articles/create', { /* 无效数据 */ })
} catch (e) {
  if (e instanceof ValidationError) {
    e.fields              // { title: ['required'] }
  } else if (e instanceof ForbiddenError) {
    /* 403 */
  }
}
```

## Store（Pinia）

```ts
import { useAuthStore, useManifestStore, useNotificationsStore } from '@dskripchenko/laravel-admin'
```

从包根导出：`useAuthStore`、`useManifestStore`、`useMenuStore`、`useThemeStore`、
`useLocaleStore`、`useNotificationsStore`、`useResourceIndexStore`、
`useResourceFormStore`、`useI18nStore`。Screen、仪表板和导航相关的 store 属于 SPA
页面内部实现，不是公共 API 的一部分。

## 另请参阅

- [字段参考](fields-reference.md)
- [布局参考](layouts-reference.md)
- [架构](architecture.md)
