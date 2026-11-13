<script setup lang="ts">
/**
 * An action placed among a screen's layouts — a Button in Rows, in a Columns
 * cell next to a line of data — drawn as a toolbar button. A click goes
 * through the screen context: the confirmation, an overlay it opens, or the
 * screen method with the button's own parameters (Button::parameters()).
 *
 * Outside a screen (a resource form) there is nowhere to send it, so nothing
 * is drawn.
 */
import { computed } from 'vue'
import AdminActionButton from '../actions/AdminActionButton.vue'
import { normalizeAction, type AdminAction } from '../../composables/useActionRunner'
import { useScreenContext } from './screenContext'

interface Props {
  node: Record<string, unknown>
}
const props = defineProps<Props>()

const screen = useScreenContext()
const action = computed(() => normalizeAction(props.node))

async function run(a: AdminAction): Promise<void> {
  if (screen) await screen.dispatch(a)
}
</script>

<template>
  <div v-if="action && screen" class="admin-layout-action">
    <AdminActionButton
      :action="action"
      size="sm"
      :disabled="screen.running.value"
      @run="run"
    />
  </div>
</template>

<style>
.admin-layout-action { display: flex; align-items: center; }
</style>
