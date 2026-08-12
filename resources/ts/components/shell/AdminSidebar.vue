<script setup lang="ts">
/**
 * The admin shell's sidebar, built on UidSidebar, UidSidebarGroup and
 * UidSidebarItem.
 *
 * The data comes from useMenuStore — groupedItems, already filtered by
 * permission through auth.hasAnyPermission. UidSidebarItem supports a nested
 * icon slot plus active and badge, and those map straight from a MenuItem.
 *
 * A brand row on top, an optional tenant block and a footer with the version
 * and the docs link, following
 * docs/design_handoff_laravel_admin/screens-shell.jsx (Sidebar).
 */
import { computed } from 'vue'
import { UidSidebar, UidSidebarGroup, UidSkeleton } from '@dskripchenko/ui'
import { useMenuStore } from '../../stores/menu'
import { useAppReady } from '../../composables/useAppReady'
import AdminSidebarNode from './AdminSidebarNode.vue'
import BrandLogo from './BrandLogo.vue'

interface Props {
  collapsed?: boolean
  /** The brand's title. */
  brandName?: string
  /**
   * A custom mark; when set it is rendered instead of BrandLogo. Useful to a
   * host project with a logo of its own.
   */
  brandMark?: string | null
  /** The logo image's URL; it wins over brandMark and BrandLogo. */
  brandLogo?: string | null
  /** The named route a click on the brand row leads to. */
  homeRouteName?: string
  /** The tenant or workspace, optionally shown below the brand. */
  tenant?: { label: string; name: string } | null
  /** The version and the docs link in the footer. */
  version?: string | null
  docsUrl?: string | null
}

withDefaults(defineProps<Props>(), {
  collapsed: false,
  brandName: 'Laravel Admin',
  brandMark: null,
  brandLogo: null,
  homeRouteName: 'admin.home',
  tenant: null,
  version: null,
  docsUrl: null,
})

const menu = useMenuStore()

const groups = computed(() => menu.groupedItems)

/**
 * Until the shell is ready the items are replaced by their silhouette: an
 * empty column next to a drawn topbar reads as "the menu broke", not as "it is
 * about to arrive". The gate is shared with the page, so the menu and the
 * content appear together.
 */
const appReady = useAppReady()
// When the items are already there — the host set them directly, or the
// answer arrived before the manifest — there is no point hiding ready data
// behind a silhouette.
const ready = computed(() => appReady.value || menu.isLoaded)
</script>

<template>
  <UidSidebar :collapsed="collapsed">
    <template #header>
      <router-link
        :to="{ name: homeRouteName }"
        class="admin-sidebar-brand"
        :title="collapsed ? brandName : undefined"
        :aria-label="brandName"
      >
        <img v-if="brandLogo" class="admin-sidebar-brand__logo" :src="brandLogo" :alt="brandName" />
        <div v-else-if="brandMark" class="admin-sidebar-brand__mark">{{ brandMark }}</div>
        <BrandLogo v-else :size="28" />
        <div v-if="!collapsed" class="admin-sidebar-brand__name">{{ brandName }}</div>
      </router-link>
      <div v-if="tenant && !collapsed" class="admin-sidebar-tenant">
        <span>{{ tenant.label }}</span>
        <b>{{ tenant.name }}</b>
      </div>
    </template>

    <template #nav>
      <div v-if="!ready" class="admin-sidebar-boot" aria-busy="true">
        <UidSkeleton v-for="i in 6" :key="`sk-${i}`" height="32px" />
      </div>
      <template v-else>
        <UidSidebarGroup
          v-for="(grp, idx) in groups"
          :key="`grp-${idx}`"
          :title="grp.group ?? undefined"
        >
          <AdminSidebarNode
            v-for="item in grp.items"
            :key="item.key"
            :item="item"
            :collapsed="collapsed"
          />
        </UidSidebarGroup>
      </template>
    </template>

    <template #footer>
      <div class="admin-sidebar-foot">
        <span class="admin-sidebar-foot__text" :title="version ?? brandName">
          <template v-if="!collapsed">{{ version ?? brandName }}</template>
          <template v-else>·</template>
        </span>
        <a
          v-if="docsUrl && !collapsed"
          :href="docsUrl"
          class="admin-sidebar-foot__text admin-sidebar-foot__link"
        >
          Docs
        </a>
      </div>
    </template>
  </UidSidebar>
</template>

<style>
/* The menu's silhouette while the shell loads, following the items' geometry. */
.admin-sidebar-boot {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 8px 12px;
}

/*
 * The sidebar header is aligned with AdminTopBar: 56px of height plus a 1px
 * border. The padding of uid-pattern-sidebar__header is zeroed, so the brand
 * occupies exactly one header row. The UI kit's border-bottom stays — it sits
 * at the same Y as the topbar's and forms one horizontal above the nav and the
 * content.
 */
.uid-pattern-sidebar__header:has(.admin-sidebar-brand) {
  padding: 0;
}
.admin-sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 56px;
  padding: 0 16px;
  text-decoration: none;
  color: inherit;
  transition: background var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease);
}
.admin-sidebar-brand:hover {
  background: var(--uid-color-surface-hover, rgba(0, 0, 0, 0.04));
}
.admin-sidebar-brand:focus-visible {
  outline: 2px solid var(--uid-color-focus-ring, var(--uid-accent));
  outline-offset: -2px;
}
.admin-sidebar-brand__logo {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  flex: none;
  display: block;
}
.admin-sidebar-brand__mark {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  flex: none;
  background: var(--uid-text-primary);
  color: var(--uid-surface-raised);
  display: grid;
  place-items: center;
  font-family: var(--uid-font-family-display);
  font-weight: var(--uid-font-weight-bold);
  font-size: 14px;
  letter-spacing: -0.02em;
}
.admin-sidebar-brand__name {
  font-family: var(--uid-font-family-display);
  font-size: 14px;
  font-weight: var(--uid-font-weight-semibold);
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  color: var(--uid-text-primary);
}
.admin-sidebar-tenant {
  margin: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 32px;
  padding: 0 8px;
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
  background: var(--uid-surface-base);
}
.admin-sidebar-tenant b { color: var(--uid-text-primary); font-weight: 500; }
.admin-sidebar-foot {
  /*
   * The height comes strictly from --admin-foot-height and matches
   * .admin-main-footer (see AdminShell.vue), so that the sidebar foot's
   * border-top and the main footer's sit at the same Y and give one horizontal
   * beneath the screen.
   */
  height: var(--admin-foot-height, 32px);
  padding: 0 16px;
  border-top: 1px solid var(--uid-border-subtle);
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  color: var(--uid-text-tertiary);
}
.admin-sidebar-foot__link { cursor: pointer; }
.admin-sidebar-foot__link:hover { color: var(--uid-text-primary); }

.admin-sidebar__icon {
  width: 16px;
  height: 16px;
  display: inline-block;
  flex: none;
  color: currentColor;
}
</style>
