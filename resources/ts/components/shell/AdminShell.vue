<script setup lang="ts">
/**
 * Корневой layout admin-панели поверх UidSidebarLayout из @dskripchenko/ui.
 *
 * Slots:
 *   - sidebar — обычно AdminSidebar (default)
 *   - header — AdminTopBar (default)
 *   - default — main-area (host рендерит <RouterView/>)
 *
 * v-model — boolean флаг сворачивания сайдбара (240→56 px, transition в uid).
 *
 * Impersonation: prop `impersonation` показывает 32-px amber бейдж над shell'ом
 * с кнопкой выхода — стиль из docs/design_handoff_laravel_admin/app.css:159
 * (.imp-banner). На <html> ставим data-impersonating='true' чтобы корректно
 * сместить sticky-элементы вниз.
 */
import { onBeforeUnmount, watch } from 'vue'
import { UidSidebarLayout } from '@dskripchenko/ui'
import AdminTopBar from './AdminTopBar.vue'
import AdminSidebar from './AdminSidebar.vue'

interface ImpersonationData {
  /** Имя того, в кого вошли. */
  asName: string
}

interface Props {
  /** Сворачивание сайдбара. */
  collapsed?: boolean
  /** Если задано — показывает amber-banner и сдвигает контент. */
  impersonation?: ImpersonationData | null
}
const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  impersonation: null,
})

const emit = defineEmits<{
  'update:collapsed': [value: boolean]
  'exit-impersonation': []
}>()

function onCollapseChange(value: boolean): void {
  emit('update:collapsed', value)
}

function exitImpersonation(): void {
  emit('exit-impersonation')
}

// Маркер на <html> — для shell-classes которые делают paddding-top + sticky-offset.
const HTML_ATTR = 'data-admin-impersonating'

watch(
  () => props.impersonation,
  (val) => {
    if (typeof document === 'undefined') return
    if (val !== null) document.documentElement.setAttribute(HTML_ATTR, 'true')
    else document.documentElement.removeAttribute(HTML_ATTR)
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') {
    document.documentElement.removeAttribute(HTML_ATTR)
  }
})
</script>

<template>
  <div class="admin-shell-root">
    <div v-if="impersonation" class="admin-impersonation-banner" role="status">
      <span>
        Вы вошли как <b>{{ impersonation.asName }}</b> · режим имперсонации
      </span>
      <button
        type="button"
        class="admin-impersonation-banner__exit"
        @click="exitImpersonation"
      >
        Выйти из режима
      </button>
    </div>
    <UidSidebarLayout
      :model-value="collapsed"
      @update:model-value="onCollapseChange"
    >
      <template #sidebar>
        <slot name="sidebar">
          <AdminSidebar :collapsed="collapsed" />
        </slot>
      </template>
      <template #header>
        <slot name="topbar">
          <AdminTopBar @toggle-sidebar="onCollapseChange(!collapsed)" />
        </slot>
      </template>
      <slot />
    </UidSidebarLayout>
  </div>
</template>

<style>
.admin-shell-root[data-admin-impersonating='true'] {
  /* Sidebar/topbar sticky уже учтут `--admin-page-pad` и offset через CSS-классы. */
  padding-top: 32px;
}
:root[data-admin-impersonating='true'] {
  scroll-padding-top: 32px;
}
</style>
