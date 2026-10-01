<script setup lang="ts">
/**
 * Actions as items of an existing UidMenu. A DropDown nested here becomes a
 * UidSubMenu holding its own actions, to any depth.
 */
import { UidIcon, UidMenuItem, UidSubMenu } from '@dskripchenko/ui'
import type { AdminAction } from '../../composables/useActionRunner'
import { resolveIcon } from '../shell/iconRegistry'
import AdminActionMenuItems from './AdminActionMenuItems.vue'

interface Props {
  actions: AdminAction[]
  /** Whether an action is unavailable, e.g. for the current selection size. */
  isDisabled?: (action: AdminAction) => boolean
}

withDefaults(defineProps<Props>(), { isDisabled: () => false })

const emit = defineEmits<{ run: [action: AdminAction] }>()
</script>

<template>
  <template v-for="action in actions" :key="action.name">
    <UidSubMenu
      v-if="action.type === 'dropdown'"
      :label="action.label"
      :icon="resolveIcon(action.icon) ?? undefined"
      :data-testid="`action-${action.name}`"
    >
      <AdminActionMenuItems
        :actions="action.items"
        :is-disabled="isDisabled"
        @run="(a: AdminAction) => emit('run', a)"
      />
    </UidSubMenu>
    <UidMenuItem
      v-else
      :variant="action.destructive ? 'danger' : 'default'"
      :disabled="isDisabled(action)"
      :data-testid="`action-${action.name}`"
      @click="emit('run', action)"
    >
      <template v-if="resolveIcon(action.icon)" #icon>
        <UidIcon :icon="resolveIcon(action.icon)!" :size="14" />
      </template>
      {{ action.label }}
    </UidMenuItem>
  </template>
</template>
