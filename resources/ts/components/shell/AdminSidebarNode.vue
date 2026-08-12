<script setup lang="ts">
import { computed, h, ref, watch, type Component } from 'vue'
import { useRoute } from 'vue-router'
import { Box, ChevronRight } from 'lucide-vue-next'
import { UidIcon, UidSidebarItem } from '@dskripchenko/ui'
import type { MenuItem } from '../../stores/menu'
import { resolveIcon } from './iconRegistry'

interface Props {
  item: MenuItem
  depth?: number
  collapsed?: boolean
  /** The depth past which the stripe mode replaces a growing indent. */
  stripeAt?: number
  /** How many pixels each level indents by, up to stripeAt. */
  indentStep?: number
}

const props = withDefaults(defineProps<Props>(), {
  depth: 0,
  collapsed: false,
  stripeAt: 3,
  indentStep: 14,
})

const route = useRoute()

const hasChildren = computed(
  () => Array.isArray(props.item.children) && props.item.children!.length > 0,
)

const open = ref(false)

function isActive(item: MenuItem): boolean {
  // An exact match
  if (item.routeName && route.name === item.routeName) return true
  if (item.url && route.path === item.url) return true
  // A prefix match: the list route stays active on the resource's detail
  // pages — resource.{slug}.list covers .create, .{id}.edit and .{id}.view.
  if (item.routeName && typeof route.name === 'string') {
    const base = String(item.routeName).replace(/\.(list|index)$/, '')
    if (route.name.startsWith(base + '.')) return true
  }
  if (item.url && route.path.startsWith(item.url + '/')) return true
  return false
}

function containsActive(item: MenuItem): boolean {
  if (isActive(item)) return true
  return (item.children ?? []).some(containsActive)
}

const groupActive = computed(() => containsActive(props.item))

watch(
  () => groupActive.value,
  (val) => { if (val) open.value = true },
  { immediate: true },
)

const itemTarget = computed<string | Record<string, unknown> | undefined>(() => {
  if (props.item.url) return props.item.url
  if (props.item.routeName) return { name: props.item.routeName }
  return undefined
})

/**
 * The indent actually applied: it grows up to stripeAt, then freezes at
 * stripeAt-1 and the stripe mode — a left border — takes over.
 */
const indentDepth = computed(() => Math.min(props.depth, props.stripeAt - 1))

const stripeStep = computed(() => Math.max(0, props.depth - (props.stripeAt - 1)))

/**
 * The stripe's colour by depth: the alpha steps down from 0.85 to 0.15 by
 * 0.18 at a time, with a floor of 0.12 so that at depth five and beyond the
 * stripe stays visible.
 */
const stripeAlpha = computed(() => {
  const step = stripeStep.value
  if (step === 0) return 0
  return Math.max(0.12, 0.85 - (step - 1) * 0.18)
})

function iconNode(name?: string | null): Component {
  const resolved = resolveIcon(name) ?? Box
  return () => h(UidIcon, { icon: resolved, size: 16 })
}

function toggle(): void {
  open.value = !open.value
}
</script>

<template>
  <div
    class="admin-sidebar-node"
    :class="{
      'admin-sidebar-node--has-children': hasChildren,
      'admin-sidebar-node--open': open,
      'admin-sidebar-node--active': groupActive,
      'admin-sidebar-node--stripe': stripeStep > 0,
    }"
    :style="{
      '--admin-sidebar-indent': `${indentDepth * indentStep}px`,
      '--admin-sidebar-stripe-alpha': stripeAlpha,
    }"
    :data-depth="depth"
  >
    <button
      v-if="hasChildren"
      type="button"
      class="admin-sidebar-node__group uid-sidebar-item"
      :class="{
        'uid-sidebar-item--active': groupActive,
      }"
      :aria-expanded="open"
      :title="collapsed ? item.label : undefined"
      @click="toggle"
    >
      <span class="uid-sidebar-item__icon">
        <component :is="iconNode(item.icon)" />
      </span>
      <span v-if="!collapsed" class="uid-sidebar-item__label">{{ item.label }}</span>
      <span
        v-if="!collapsed"
        class="admin-sidebar-node__chev"
        :class="{ 'admin-sidebar-node__chev--open': open }"
        aria-hidden="true"
      >
        <UidIcon :icon="ChevronRight" :size="14" />
      </span>
    </button>

    <UidSidebarItem
      v-else
      class="admin-sidebar-node__leaf"
      :data-testid="`menu-${item.key}`"
      :to="itemTarget"
      :active="isActive(item)"
      :badge="item.badge ?? undefined"
      :title="collapsed ? item.label : undefined"
    >
      <template #icon>
        <component :is="iconNode(item.icon)" />
      </template>
      {{ item.label }}
    </UidSidebarItem>

    <div
      v-if="hasChildren && open"
      class="admin-sidebar-node__children"
      role="group"
    >
      <AdminSidebarNode
        v-for="child in item.children"
        :key="child.key"
        :item="child"
        :depth="depth + 1"
        :collapsed="collapsed"
        :stripe-at="stripeAt"
        :indent-step="indentStep"
      />
    </div>
  </div>
</template>

<style>
.admin-sidebar-node {
  --admin-sidebar-stripe-color: color-mix(
    in srgb,
    var(--uid-color-primary, #14b8a6) calc(var(--admin-sidebar-stripe-alpha, 0) * 100%),
    transparent
  );
}

/*
 * The indent is applied to the row itself — the item or the group button —
 * rather than to the wrapper: otherwise the hover and active backgrounds
 * break, and those must span the sidebar's full width. The inner
 * uid-sidebar-item keeps its margin.
 */
.admin-sidebar-node > .admin-sidebar-node__group,
.admin-sidebar-node > .admin-sidebar-node__leaf {
  padding-inline-start: calc(12px + var(--admin-sidebar-indent, 0px));
}

/*
 * The stripe mode draws a vertical bar on the left inside the row, through an
 * inset box-shadow, so that the text does not shift and the row's padding
 * stays intact. The colour is a semi-transparent primary, and the alpha comes
 * from --admin-sidebar-stripe-alpha, which the component recomputes per depth.
 */
.admin-sidebar-node--stripe > .admin-sidebar-node__group,
.admin-sidebar-node--stripe > .admin-sidebar-node__leaf {
  box-shadow: inset 2px 0 0 0 var(--admin-sidebar-stripe-color);
}

/* The uid-sidebar-item base layout for a button; UidSidebarItem covers the leaves alone */
.admin-sidebar-node__group {
  width: calc(100% - var(--uid-space-sm) * 2);
  margin: 1px var(--uid-space-sm);
  border: 0;
  background: transparent;
  cursor: pointer;
  font: inherit;
  color: var(--uid-sidebar-item-color, var(--uid-color-text-secondary));
  text-align: left;
}

.admin-sidebar-node__chev {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  margin-inline-start: auto;
  color: var(--uid-color-text-tertiary);
  transition: transform var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease);
}
.admin-sidebar-node__chev--open { transform: rotate(90deg); }

.admin-sidebar-node--has-children.admin-sidebar-node--active > .admin-sidebar-node__group {
  color: var(--uid-color-text-primary);
}

.admin-sidebar-node__children {
  display: flex;
  flex-direction: column;
}

/* While collapsed the nested levels are hidden — the sidebar is compact as it is. */
.uid-pattern-sidebar--collapsed .admin-sidebar-node__children { display: none; }
.uid-pattern-sidebar--collapsed .admin-sidebar-node__chev { display: none; }
</style>
