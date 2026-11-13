<script setup lang="ts">
/**
 * The palette of the block tree: the types allowed at the chosen place,
 * grouped, with a search, a description and a thumbnail when the type has
 * them. A pick inserts the block there.
 */
import { computed, nextTick, onMounted, ref } from 'vue'
import { UidButton, UidIcon, UidInput } from '@dskripchenko/ui'
import { Box, X } from 'lucide-vue-next'
import { resolveIcon } from '../shell/iconRegistry'
import { trSafe as tr } from '../../stores/i18n'
import type { BlockTypeDef } from './tree'

const props = defineProps<{
  types: BlockTypeDef[]
  /** Where the block goes, for the heading: the parent's label, or null for the top level. */
  place: string | null
}>()

const emit = defineEmits<{ pick: [def: BlockTypeDef]; close: [] }>()

const query = ref('')
const root = ref<HTMLElement | null>(null)

const groups = computed(() => {
  const q = query.value.trim().toLocaleLowerCase()
  const found = props.types.filter(
    (t) => q === '' || t.label.toLocaleLowerCase().includes(q) || (t.description ?? '').toLocaleLowerCase().includes(q),
  )
  const byGroup = new Map<string, BlockTypeDef[]>()
  for (const t of found) {
    const g = t.group ?? ''
    byGroup.set(g, [...(byGroup.get(g) ?? []), t])
  }
  return [...byGroup.entries()].map(([name, list]) => ({ name, list }))
})

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  void nextTick(() => root.value?.querySelector<HTMLInputElement>('input')?.focus())
})
</script>

<template>
  <div ref="root" class="admin-block-tree-palette" role="dialog" :aria-label="tr('Добавить блок')" @keydown="onKey">
    <div class="admin-block-tree-palette__head">
      <strong>{{ place ? `${tr('Добавить в')} «${place}»` : tr('Добавить блок') }}</strong>
      <UidButton size="sm" variant="ghost" :aria-label="tr('Закрыть')" @click="emit('close')">
        <UidIcon :icon="X" :size="16" />
      </UidButton>
    </div>
    <UidInput v-model="query" size="sm" :placeholder="tr('Поиск по типам')" />
    <div class="admin-block-tree-palette__list">
      <p v-if="groups.length === 0" class="admin-block-tree-palette__empty">{{ tr('Сюда нельзя добавить ни один тип') }}</p>
      <section v-for="g in groups" :key="g.name">
        <h4 v-if="g.name" class="admin-block-tree-palette__group">{{ g.name }}</h4>
        <button
          v-for="t in g.list"
          :key="t.type"
          type="button"
          class="admin-block-tree-palette__item"
          :data-type="t.type"
          @click="emit('pick', t)"
        >
          <img v-if="t.thumbnail" class="admin-block-tree-palette__thumb" :src="t.thumbnail" alt="" loading="lazy" />
          <UidIcon v-else :icon="resolveIcon(t.icon) ?? Box" :size="18" />
          <span class="admin-block-tree-palette__text">
            <span>{{ t.label }}</span>
            <small v-if="t.description">{{ t.description }}</small>
          </span>
        </button>
      </section>
    </div>
  </div>
</template>
