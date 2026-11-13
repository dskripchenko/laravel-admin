<script setup lang="ts">
/**
 * AdminLinkDrawer — the side panel a link with `target: 'drawer'` opens over
 * the current page (stores/linkDrawer): «Связанное» of a contract card, the
 * client's other records from a queue.
 *
 * The link is a panel address. A resource's list (`/r/{slug}?filter[…]=…&q=…&scope=…`)
 * opens filtered and scoped as its query says — the user may change the
 * filters there, the address of the page under it stays; a screen
 * (`/screens/{slug}?…`) opens embedded with that query. Anything else is
 * opened as a page. «Открыть страницей» takes the panel's address to the page.
 */
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ExternalLink } from 'lucide-vue-next'
import { UidButton, UidDrawer, UidIcon } from '@dskripchenko/ui'
import { useLinkDrawerStore } from '../../stores/linkDrawer'
import { useAuthStore } from '../../stores/auth'
import { toRouterPath } from '../../composables/useActionRunner'
import ResourceIndexPage from '../resource/ResourceIndexPage.vue'
import ScreenPage from '../ScreenPage.vue'
import AdminState from '../states/AdminState.vue'
import { trSafe as tr } from '../../stores/i18n'

type Target =
  | { kind: 'resource'; slug: string; query: Record<string, unknown>; scope: string | null; path: string; title: string; allowed: boolean; permission: string }
  | { kind: 'screen'; slug: string; params: Record<string, string>; path: string; title: string; allowed: boolean; permission: string }
  | { kind: 'page'; path: string }
  | { kind: 'external'; url: string }

const store = useLinkDrawerStore()
const router = useRouter()
const auth = useAuthStore()

const WIDTHS: Record<string, string> = { sm: '420px', md: '560px', lg: '720px', xl: '960px' }
const width = computed<string>(() => (store.size ? WIDTHS[store.size] ?? store.size : 'min(1100px, 92vw)'))

function allowedBy(meta: Record<string, unknown>): { allowed: boolean; permission: string } {
  const keys = Array.isArray(meta.permissions) ? (meta.permissions as string[]) : []
  if (keys.length === 0) return { allowed: true, permission: '' }
  const allowed = meta.permissionMode === 'all' ? auth.hasAllPermissions(keys) : auth.hasAnyPermission(keys)
  return { allowed, permission: keys.join(' | ') }
}

const target = computed<Target | null>(() => {
  const url = store.url
  if (url === null) return null
  const path = toRouterPath(router, url)
  if (path === null) return { kind: 'external', url }
  const resolved = router.resolve(path)
  const meta = (resolved.meta ?? {}) as Record<string, unknown>
  const title = typeof meta.title === 'string' ? meta.title : ''
  if (meta.kind === 'resource' && typeof resolved.name === 'string' && resolved.name.endsWith('.index') && typeof meta.slug === 'string') {
    const scope = typeof resolved.query.scope === 'string' && resolved.query.scope !== '' ? resolved.query.scope : null
    return { kind: 'resource', slug: meta.slug, query: { ...resolved.query }, scope, path, title, ...allowedBy(meta) }
  }
  if (meta.kind === 'screen' && typeof meta.slug === 'string') {
    const params: Record<string, string> = {}
    for (const [k, v] of Object.entries(resolved.query)) {
      if (typeof v === 'string') params[k] = v
    }
    return { kind: 'screen', slug: meta.slug, params, path, title, ...allowedBy(meta) }
  }
  return { kind: 'page', path }
})

// What the panel cannot hold opens as a page or in a new tab.
watch(target, (next) => {
  if (next === null) return
  if (next.kind === 'page') {
    store.close()
    void router.push(next.path).catch(() => undefined)
  } else if (next.kind === 'external') {
    store.close()
    if (typeof window !== 'undefined') window.open(next.url, '_blank', 'noopener')
  }
})

const shown = computed(() => {
  const t = target.value
  return t !== null && (t.kind === 'resource' || t.kind === 'screen') ? t : null
})
const open = computed<boolean>(() => shown.value !== null)
const title = computed<string>(() => store.title ?? shown.value?.title ?? '')

function openAsPage(): void {
  const t = shown.value
  if (!t) return
  store.close()
  void router.push(t.path).catch(() => undefined)
}
</script>

<template>
  <UidDrawer
    :model-value="open"
    :title="title"
    side="right"
    :width="width"
    @update:model-value="(v: boolean) => { if (!v) store.close() }"
  >
    <div v-if="shown" :key="store.serial" class="admin-link-drawer" data-testid="link-drawer" :data-kind="shown.kind">
      <div class="admin-link-drawer__bar">
        <UidButton variant="ghost" size="sm" data-testid="link-drawer-page" @click="openAsPage">
          <template #prepend><UidIcon :icon="ExternalLink" :size="14" /></template>
          {{ tr('Открыть страницей') }}
        </UidButton>
      </div>
      <AdminState
        v-if="!shown.allowed"
        kind="forbidden"
        :section="shown.title || null"
        :permission="shown.permission"
      />
      <ResourceIndexPage
        v-else-if="shown.kind === 'resource'"
        :slug="shown.slug"
        :title="shown.title"
        :state-key="`drawer:${shown.slug}`"
        :scope="shown.scope"
        :initial-query="shown.query"
      />
      <ScreenPage
        v-else
        :slug="shown.slug"
        embedded
        :show-title="false"
        :params="shown.params"
      />
    </div>
  </UidDrawer>
</template>

<style>
.admin-link-drawer__bar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: var(--uid-space-sm, 8px);
}
.admin-link-drawer > .admin-page {
  padding: 0;
}
.admin-link-drawer .admin-page__title {
  font-size: var(--uid-font-size-md, 15px);
}
</style>
