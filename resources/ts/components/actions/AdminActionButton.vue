<script setup lang="ts">
/**
 * One action as a toolbar control: a UidButton, or — for a DropDown — a
 * UidMenu whose items are the nested actions. A click emits `run` with the
 * action to dispatch; the page passes it to its useActionRunner.
 */
import { computed } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { UidButton, UidMenu } from '@dskripchenko/ui'
import type { AdminAction } from '../../composables/useActionRunner'
import { resolveIcon } from '../shell/iconRegistry'
import AdminActionMenuItems from './AdminActionMenuItems.vue'

interface Props {
  action: AdminAction
  size?: 'sm' | 'md' | 'lg'
  /** Overrides the variant derived from primary/destructive. */
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  /** The variant of a plain (neither primary nor destructive) action. */
  defaultVariant?: 'secondary' | 'ghost'
  disabled?: boolean
  loading?: boolean
  isDisabled?: (action: AdminAction) => boolean
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  variant: undefined,
  defaultVariant: 'secondary',
  disabled: false,
  loading: false,
  isDisabled: () => false,
})

const emit = defineEmits<{ run: [action: AdminAction] }>()

const resolvedVariant = computed(() => {
  if (props.variant) return props.variant
  if (props.action.destructive) return 'danger'
  if (props.action.primary) return 'primary'
  return props.defaultVariant
})
const icon = computed(() => resolveIcon(props.action.icon))
</script>

<template>
  <UidMenu v-if="action.type === 'dropdown'">
    <template #trigger>
      <UidButton
        :size="size"
        :variant="resolvedVariant"
        :disabled="disabled"
        :icon="ChevronDown"
        icon-position="end"
        :data-testid="`action-${action.name}`"
      >
        {{ action.label }}
      </UidButton>
    </template>
    <AdminActionMenuItems
      :actions="action.items"
      :is-disabled="isDisabled"
      @run="(a: AdminAction) => emit('run', a)"
    />
  </UidMenu>
  <UidButton
    v-else
    :size="size"
    :variant="resolvedVariant"
    :disabled="disabled || isDisabled(action)"
    :loading="loading"
    :icon="icon ?? undefined"
    :data-testid="`action-${action.name}`"
    @click="emit('run', action)"
  >
    {{ action.label }}
  </UidButton>
</template>
