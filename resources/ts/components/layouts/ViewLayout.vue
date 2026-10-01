<script setup lang="ts">
/**
 * The View layout — a custom Vue component, by name, with props.
 *
 * The backend's `Layout::view('my-card', ['count' => 42])` arrives as
 * `{type: 'view', component: 'my-card', count: 42}`. The host registers the
 * component with `registerLayout('my-card', MyCard)`; it is looked up here and
 * rendered with every prop the backend gave it (and the children, if any, as
 * `items`). Nothing is rendered as HTML: an unregistered name shows a warning
 * telling the host what to register.
 */
import { computed, useAttrs } from 'vue'
import { getLayout } from '../render/registry'
import { UidAlert } from '@dskripchenko/ui'
import { tRaw } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

interface Props {
  component?: string | null
}

const props = withDefaults(defineProps<Props>(), { component: null })
const attrs = useAttrs()

const resolved = computed(() => {
  // A view naming itself would render itself forever.
  if (!props.component || props.component === 'view') return null
  return getLayout(props.component) ?? null
})

// The serialization duplicates: `props` and `children` repeat the top-level
// keys for older consumers. They are not the component's props.
const passProps = computed<Record<string, unknown>>(() => {
  const { props: _props, children: _children, ...rest } = attrs as Record<string, unknown>
  return rest
})
</script>

<template>
  <component :is="resolved" v-if="resolved" v-bind="passProps" />
  <UidAlert v-else variant="warning" class="admin-view-layout__missing">
    <template #title>{{ tRaw('Компонент не зарегистрирован: :name', { name: component ?? '' }) }}</template>
    <code>registerLayout('{{ component }}', YourComponent)</code>
  </UidAlert>
</template>
