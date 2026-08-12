<script setup lang="ts">
/**
 * The admin panel's root layout, built on UidSidebarLayout from @dskripchenko/ui.
 *
 * Slots:
 *   - sidebar — usually AdminSidebar (default)
 *   - header — AdminTopBar (default)
 *   - default — the main area (the host renders <RouterView/>)
 *
 * v-model is the sidebar collapse flag (240 → 56 px, transition lives in uid).
 *
 * Impersonation: the `impersonation` prop shows a 32-px amber badge above the
 * shell with an exit button — the style comes from
 * docs/design_handoff_laravel_admin/app.css:159 (.imp-banner). We set
 * data-impersonating='true' on <html> so that sticky elements shift down
 * correctly.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { UidSidebarLayout } from '@dskripchenko/ui'
import AdminTopBar from './AdminTopBar.vue'
import AdminSidebar from './AdminSidebar.vue'
import GlobalSearch from './GlobalSearch.vue'
import { trSafe as tr } from '../../stores/i18n'

interface ImpersonationData {
  /** Whom we are logged in as. */
  asName: string
}

interface BrandData {
  name?: string
  logo?: string | null
  /** A short textual mark (1-2 characters), used when there is no logo image. */
  mark?: string | null
  favicon?: string | null
  copyright?: string | null
  footer?: string | null
}

interface Props {
  /**
   * v-model:collapsed — the collapse state. Optional: when the host does not
   * pass it, AdminShell keeps an internal ref so that the collapse toggle
   * works out of the box.
   */
  collapsed?: boolean
  /** When set, shows the amber banner and shifts the content down. */
  impersonation?: ImpersonationData | null
  /** Branding (name/logo/copyright) from config('admin.brand'). */
  brand?: BrandData | null
}
const props = withDefaults(defineProps<Props>(), {
  collapsed: undefined,
  impersonation: null,
  brand: null,
})

const brandName = computed<string | undefined>(() => props.brand?.name || undefined)
// logo is an image URL, mark is a short text. Historically logo was passed
// into the sidebar as text — now it is an image, the same as on LoginPage.
const brandLogo = computed<string | null>(() => props.brand?.logo ?? null)
const brandMark = computed<string | null>(() => props.brand?.mark ?? null)
// brand.footer becomes the sidebar's bottom line: a version or any free text.
const brandFooter = computed<string | null>(() => props.brand?.footer ?? null)
const brandCopyright = computed<string | null>(() => props.brand?.copyright ?? null)

const emit = defineEmits<{
  'update:collapsed': [value: boolean]
  'exit-impersonation': []
}>()

// Internal fallback state, used when the host passes no v-model.
const internalCollapsed = ref<boolean>(false)

/**
 * The width below which the sidebar turns into a drawer sliding over the
 * content (`@media (max-width: 768px)` inside UidSidebarLayout). On the
 * desktop the `collapsed` flag means "a narrow sidebar", here it means "the
 * drawer is closed" — and those are different things: with the default
 * `false` the panel opened on a phone with the menu covering the whole
 * screen, and the collapse button ended up underneath that very drawer. So in
 * this mode the state is set separately.
 */
const DRAWER_BREAKPOINT = '(max-width: 768px)'
const isDrawerMode = ref<boolean>(false)
let drawerQuery: MediaQueryList | null = null

function applyDrawerMode(matches: boolean): void {
  const wasDrawer = isDrawerMode.value
  isDrawerMode.value = matches

  // Entering drawer mode closes it: the user came to read the page, not the
  // menu. Coming back to a wide screen expands the sidebar again — otherwise
  // it would stay collapsed for no reason at all.
  if (matches && !wasDrawer) onCollapseChange(true)
  if (!matches && wasDrawer) onCollapseChange(false)
}

const route = useRoute()

// Picking a menu item is a navigation, and the drawer has to go after it:
// otherwise it stays on top of the page just opened and has to be closed by
// hand on every single navigation.
watch(
  () => route.fullPath,
  () => {
    if (isDrawerMode.value) onCollapseChange(true)
  },
)
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

// A marker on <html>, for the shell classes that do padding-top + sticky-offset.
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

onMounted(() => {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return

  drawerQuery = window.matchMedia(DRAWER_BREAKPOINT)
  isDrawerMode.value = drawerQuery.matches
  if (drawerQuery.matches) onCollapseChange(true)

  drawerQuery.addEventListener('change', onDrawerQueryChange)
})

function onDrawerQueryChange(event: MediaQueryListEvent): void {
  applyDrawerMode(event.matches)
}

onBeforeUnmount(() => {
  drawerQuery?.removeEventListener('change', onDrawerQueryChange)
})

// ⌘K / Ctrl+K opens the search globally, from any focus.
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
        {{ tr('Вы вошли как') }} <b>{{ impersonation.asName }}</b> ·
        {{ tr('режим имперсонации') }}
      </span>
      <button
        type="button"
        class="admin-impersonation-banner__exit"
        @click="exitImpersonation"
      >
        {{ tr('Выйти из режима') }}
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
            :brand-logo="brandLogo"
            :version="brandFooter"
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
  /* Sidebar/topbar sticky already account for `--admin-page-pad` and the offset through CSS classes. */
  padding-top: 32px;
}
:root[data-admin-impersonating='true'] {
  scroll-padding-top: 32px;
}

/*
 * Full-viewport layout: the sidebar and the main area each own their scroll
 * container. The window itself does not scroll (overflow:hidden on the root),
 * all scrolling is local:
 *   - sidebar __nav (its own scroll when the items outgrow the height)
 *   - main-content (the content area on the right)
 *
 * The topbar inside main is always visible (flex none / position:sticky top:0).
 *
 * Every rule is scoped to `.admin-shell` so as not to touch UidSidebarLayout
 * in other contexts (the UI kit's storybook, tests).
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
  /* The UidSidebar pattern carries a 3px border-top; the admin shell's topbar
     has none, so the sidebar content sat 3px lower. Removed to align them. */
  border-top: none;
}
.admin-shell .uid-pattern-sidebar__header {
  /* Same story as the border-top above: the pattern carries a 1px
     border-bottom on the header and admin-topbar does not, so the extra pixel
     breaks the alignment between the sidebar header and the topbar. Removed
     inside the shell; the standalone kit is left alone. */
  border-bottom: none;
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

/* The impersonation banner adds 32px above the shell — adjust the height. */
.admin-shell-root[data-admin-impersonating='true'] .admin-shell.uid-layout-sidebar,
.admin-shell-root[data-admin-impersonating='true'] .admin-shell .uid-layout-sidebar__sidebar,
.admin-shell-root[data-admin-impersonating='true'] .admin-shell .uid-layout-sidebar__main {
  height: calc(100vh - 32px);
  max-height: calc(100vh - 32px);
}

/*
 * The main footer is an empty horizontal strip below the content area. Its
 * height comes from --admin-foot-height (see styles/admin.css) and matches the
 * sidebar footer's — so both bottom lines (main's border-top and the
 * sidebar's) sit at the same Y and form one horizontal across the screen.
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
