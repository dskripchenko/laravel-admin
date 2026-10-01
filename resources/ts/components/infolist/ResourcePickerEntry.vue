<script setup lang="ts">
/**
 * ResourcePickerEntry — the view of a `resource_picker` field on the view
 * page: the picked records with their previews, in the stored order, each
 * linking to the target's view page.
 *
 * The keys come from the record; the records behind them from the target's
 * search endpoint. Without the target's view permission only the keys are
 * shown.
 */
import { computed, ref, watch } from 'vue'
import { UidLink } from '@dskripchenko/ui'
import { useEntryValue } from './entryValue'
import { useAuthStore } from '../../stores/auth'
import PickerItemView from '../fields/resourcePicker/PickerItemView.vue'
import {
  fetchPickerItems,
  keysOf,
  sameKey,
  type PickerItem,
} from '../fields/resourcePicker/pickerApi'

interface Props {
  name?: string
  label?: string
  value?: unknown
  resource?: string
  viewPermission?: string | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  resource: '',
  viewPermission: null,
  placeholder: '—',
})

const raw = useEntryValue(props)
const auth = useAuthStore()
const keys = computed(() => keysOf(raw.value))
const canView = computed<boolean>(() => (props.viewPermission ? auth.hasPermission(props.viewPermission) : true))
const loaded = ref<PickerItem[]>([])

watch(
  keys,
  async (next) => {
    if (!canView.value || !props.resource || next.length === 0) {
      loaded.value = []
      return
    }
    try {
      loaded.value = await fetchPickerItems(props.resource, next)
    } catch {
      loaded.value = []
    }
  },
  { immediate: true },
)

const items = computed<PickerItem[]>(() =>
  keys.value.map((k) => loaded.value.find((i) => sameKey(i.id, k)) ?? { id: k, title: `#${k}`, subtitle: null, preview: null }),
)

const linkOf = (item: PickerItem): string | null =>
  canView.value && props.resource ? `/r/${props.resource}/${encodeURIComponent(String(item.id))}` : null
</script>

<template>
  <span v-if="items.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <ul v-else class="admin-infolist-picker" :data-testid="`resource-picker-entry-${name}`">
    <li v-for="item in items" :key="String(item.id)" class="admin-infolist-picker__item">
      <UidLink v-if="linkOf(item)" :to="linkOf(item) ?? undefined" class="admin-infolist-picker__link">
        <PickerItemView :item="item" />
      </UidLink>
      <PickerItemView v-else :item="item" />
    </li>
  </ul>
</template>

<style>
.admin-infolist-picker {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-sm, 8px) var(--uid-space-md, 12px);
}
.admin-infolist-picker__link {
  text-decoration: none;
}
</style>
