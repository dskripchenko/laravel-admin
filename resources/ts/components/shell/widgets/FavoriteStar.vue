<script setup lang="ts">
/**
 * The star in the top bar: adds the page — with its query, so a screen's tab
 * or a list's filters too — to the user's favourites, or takes it out. The
 * favourites are listed above the menu and in the ⌘K palette.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { Star } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { useUserNavStore } from '../../../stores/userNav'
import { toastError } from '../../../stores/toast'
import { trSafe as tr } from '../../../stores/i18n'

interface Props {
  /** What the favourite is called — the page's title, with its tab. */
  label: string
}
const props = defineProps<Props>()

const route = useRoute()
const userNav = useUserNavStore()

const url = computed(() => route.fullPath)
const active = computed(() => userNav.isFavorite(url.value))
const disabled = computed(() => !active.value && userNav.favoritesFull)
const title = computed(() => {
  if (active.value) return tr('Убрать из избранного')
  if (disabled.value) return tr('В избранном уже максимум страниц')
  return tr('Добавить в избранное')
})

async function toggle(): Promise<void> {
  if (props.label === '' || disabled.value) return
  try {
    await userNav.toggleFavorite({ url: url.value, label: props.label })
  } catch (err) {
    toastError(err)
  }
}
</script>

<template>
  <button
    v-if="label !== ''"
    type="button"
    class="admin-topbar__icon-btn admin-favorite-star"
    :class="{ 'admin-favorite-star--on': active }"
    :aria-pressed="active"
    :aria-label="title"
    :title="title"
    :disabled="disabled"
    data-testid="favorite-toggle"
    @click="toggle"
  >
    <UidIcon :icon="Star" :size="16" />
  </button>
</template>

<style>
.admin-favorite-star--on {
  color: var(--uid-color-warning-solid, #f59e0b);
}
.admin-favorite-star--on svg {
  fill: currentColor;
}
.admin-favorite-star:disabled {
  opacity: 0.4;
  cursor: default;
}
</style>
