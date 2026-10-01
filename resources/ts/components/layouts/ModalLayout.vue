<script setup lang="ts">
/**
 * The Modal layout — a UidModal around the layout's children.
 *
 * It is opened by an action carrying `attributes.opens` = this layout's id
 * (the backend's `Action::opens()`), see ./overlay.ts. The footer holds the
 * backend's `->footer([...])` actions; a non-dismissable modal hides the cross
 * and stays open on the overlay click and Escape.
 */
import { computed } from 'vue'
import { UidButton, UidModal, UidStack } from '@dskripchenko/ui'
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
  /** 'sm' | 'md' | 'lg' | 'xl' | 'full'. */
  size?: string | null
  dismissable?: boolean
  footer?: ScreenActionLike[]
  open?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  id: null,
  items: () => [],
  title: null,
  size: null,
  dismissable: true,
  footer: () => [],
  open: false,
})

const overlay = useOverlay(props)
const model = overlay.model

type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full'
const SIZES: ReadonlySet<string> = new Set(['sm', 'md', 'lg', 'xl', 'full'])

const uidSize = computed<ModalSize>(() =>
  props.size && SIZES.has(props.size) ? (props.size as ModalSize) : 'md',
)
</script>

<template>
  <UidModal
    v-model="model"
    :title="title ?? undefined"
    :size="uidSize"
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
  </UidModal>
</template>
