<script setup lang="ts">
/**
 * The Drawer layout — a UidDrawer around the layout's children.
 *
 * It opens and closes like the Modal layout (see ./overlay.ts): an action
 * carrying `attributes.opens` = this layout's id. `size` is the panel's width
 * for a left/right drawer and its height for a top/bottom one: a token (sm, md,
 * lg, xl) or any CSS length.
 */
import { computed } from 'vue'
import { UidButton, UidDrawer, UidStack } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import { resolveIcon } from '../shell/iconRegistry'
import type { LayoutNode } from '../render/LayoutRenderer.vue'
import type { ScreenActionLike } from '../render/screenContext'
import { useOverlay } from './overlay'

defineOptions({ inheritAttrs: false })

interface Props {
  id?: string | null
  items?: LayoutNode[]
  title?: string | null
  /** 'left' | 'right' | 'top' | 'bottom'. */
  position?: string | null
  /** sm | md | lg | xl or a CSS length: a left/right drawer's width, a top/bottom one's height. */
  size?: string | null
  dismissable?: boolean
  footer?: ScreenActionLike[]
  open?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  id: null,
  items: () => [],
  title: null,
  position: 'right',
  size: null,
  dismissable: true,
  footer: () => [],
  open: false,
})

const overlay = useOverlay(props)
const model = overlay.model

type DrawerSide = 'left' | 'right' | 'top' | 'bottom'
const SIDES: ReadonlySet<string> = new Set(['left', 'right', 'top', 'bottom'])

const side = computed<DrawerSide>(() =>
  props.position && SIDES.has(props.position) ? (props.position as DrawerSide) : 'right',
)

const vertical = computed(() => side.value === 'top' || side.value === 'bottom')

const WIDTHS: Record<string, string> = { sm: '320px', md: '480px', lg: '640px', xl: '800px' }
const HEIGHTS: Record<string, string> = { sm: '25vh', md: '40vh', lg: '60vh', xl: '80vh' }

const dimensions = computed<{ width?: string; height?: string }>(() => {
  if (!props.size) return {}
  return vertical.value
    ? { height: HEIGHTS[props.size] ?? props.size }
    : { width: WIDTHS[props.size] ?? props.size }
})
</script>

<template>
  <UidDrawer
    v-model="model"
    :title="title ?? undefined"
    :side="side"
    v-bind="dimensions"
    :close-on-overlay="dismissable"
    :close-on-esc="dismissable"
    :hide-close="!dismissable"
  >
    <UidStack direction="column" gap="var(--uid-space-md)" align="stretch">
      <LayoutRenderer v-for="(child, idx) in items" :key="idx" :node="child" />
    </UidStack>
    <template v-if="footer.length > 0" #footer>
      <UidButton
        v-for="(action, idx) in footer"
        :key="action.name ?? idx"
        :variant="overlay.variantOf(action)"
        :icon="resolveIcon(action.icon) ?? undefined"
        :loading="overlay.running.value"
        :disabled="overlay.running.value"
        @click="overlay.onFooterClick(action)"
      >
        {{ action.label }}
      </UidButton>
    </template>
  </UidDrawer>
</template>
