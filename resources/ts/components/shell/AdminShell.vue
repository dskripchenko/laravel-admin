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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { UidSidebarLayout } from '@dskripchenko/ui'
import AdminTopBar from './AdminTopBar.vue'
import AdminSidebar from './AdminSidebar.vue'
import GlobalSearch from './GlobalSearch.vue'

interface ImpersonationData {
  /** Имя того, в кого вошли. */
  asName: string
}

interface BrandData {
  name?: string
  logo?: string | null
  favicon?: string | null
  copyright?: string | null
  footer?: string | null
}

interface Props {
  /**
   * v-model:collapsed — состояние сворачивания. Опционально: если host
   * не передаёт, AdminShell использует internal ref'ом, чтобы collapse
   * toggle работал out-of-the-box.
   */
  collapsed?: boolean
  /** Если задано — показывает amber-banner и сдвигает контент. */
  impersonation?: ImpersonationData | null
  /** Брендинг (name/logo/copyright) из config('admin.brand') (BL-12). */
  brand?: BrandData | null
}
const props = withDefaults(defineProps<Props>(), {
  collapsed: undefined,
  impersonation: null,
  brand: null,
})

const brandName = computed<string | undefined>(() => props.brand?.name || undefined)
const brandMark = computed<string | null>(() => props.brand?.logo ?? null)
const brandCopyright = computed<string | null>(() => props.brand?.copyright ?? null)

const emit = defineEmits<{
  'update:collapsed': [value: boolean]
  'exit-impersonation': []
}>()

// Internal fallback state — используется когда host не передал v-model.
const internalCollapsed = ref<boolean>(false)
const isExternallyControlled = computed<boolean>(() => props.collapsed !== undefined)
const collapsed = computed<boolean>(
  () => (isExternallyControlled.value ? (props.collapsed as boolean) : internalCollapsed.value),
)

function onCollapseChange(value: boolean): void {
  if (!isExternallyControlled.value) internalCollapsed.value = value
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

// ⌘K / Ctrl+K — глобальное открытие поиска, из любого фокуса.
const searchOpen = ref<boolean>(false)

function onGlobalKeydown(e: KeyboardEvent): void {
  if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
    e.preventDefault()
    searchOpen.value = true
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') window.addEventListener('keydown', onGlobalKeydown)
})
onBeforeUnmount(() => {
  if (typeof window !== 'undefined') window.removeEventListener('keydown', onGlobalKeydown)
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
      class="admin-shell"
      @update:model-value="onCollapseChange"
    >
      <template #sidebar>
        <slot name="sidebar">
          <AdminSidebar
            :collapsed="collapsed"
            :brand-name="brandName"
            :brand-mark="brandMark"
          />
        </slot>
      </template>
      <template #header>
        <slot name="topbar" :open-search="() => (searchOpen = true)">
          <AdminTopBar
            @toggle-sidebar="onCollapseChange(!collapsed)"
            @open-search="searchOpen = true"
          />
        </slot>
      </template>
      <slot />
      <template #footer>
        <slot name="footer">
          <!--
            Default footer-bar: горизонталь снизу контентной части, совпадает
            по Y с footer'ом sidebar'а — общая высота через --admin-foot-height.
            Host может переопределить полностью через slot=footer.
          -->
          <div class="admin-main-footer">
            <span v-if="brandCopyright" class="admin-main-footer__copyright">{{ brandCopyright }}</span>
          </div>
        </slot>
      </template>
    </UidSidebarLayout>

    <GlobalSearch v-model="searchOpen" />
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

/*
 * Full-viewport layout: sidebar и main каждый имеют own scroll контейнер.
 * Window сам не прокручивается (overflow:hidden на корне), вся прокрутка
 * локализована внутри:
 *   - sidebar __nav (свой scroll в списке пунктов когда их больше высоты)
 *   - main-content (правая контентная часть)
 *
 * Топбар внутри main всегда виден (flex none / position:sticky top:0).
 *
 * Все правила скоупированы в `.admin-shell` чтобы не задеть UidSidebarLayout
 * в других контекстах (storybook UI-kit'а, тесты).
 */
.admin-shell.uid-layout-sidebar {
  height: 100vh;
  min-height: 0;
  max-height: 100vh;
  overflow: hidden;
}
.admin-shell .uid-layout-sidebar__sidebar {
  height: 100vh;
}
.admin-shell .uid-pattern-sidebar {
  height: 100%;
  display: flex;
  flex-direction: column;
  /* UidSidebar-паттерн несёт border-top 3px; в админ-шелле topbar его не
     имеет → sidebar-контент был смещён на 3px вниз. Убираем для выравнивания. */
  border-top: none;
}
.admin-shell .uid-pattern-sidebar__nav {
  flex: 1 1 0;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}
.admin-shell .uid-pattern-sidebar__footer {
  flex: none;
}
.admin-shell .uid-layout-sidebar__main {
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-width: 0;
}
.admin-shell .uid-layout-sidebar__main-header {
  flex: none;
}
.admin-shell .uid-layout-sidebar__main-content {
  flex: 1 1 0;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

/* impersonation banner добавляет 32px над shell — корректируем высоту. */
.admin-shell-root[data-admin-impersonating='true'] .admin-shell.uid-layout-sidebar,
.admin-shell-root[data-admin-impersonating='true'] .admin-shell .uid-layout-sidebar__sidebar,
.admin-shell-root[data-admin-impersonating='true'] .admin-shell .uid-layout-sidebar__main {
  height: calc(100vh - 32px);
  max-height: calc(100vh - 32px);
}

/*
 * Main-footer — пустая горизонтальная полоса под content-area. Высоту
 * держит --admin-foot-height (см. styles/admin.css), такую же как у
 * sidebar footer'а — тогда обе нижние линии (border-top main + border-top
 * sidebar) проходят по одной Y, образуя единую горизонталь через весь экран.
 */
.admin-main-footer {
  height: var(--admin-foot-height, 32px);
  border-top: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  flex: none;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 0 var(--uid-space-md, 16px);
}
.admin-main-footer__copyright {
  font-size: 12px;
  color: var(--uid-text-tertiary);
}
</style>
