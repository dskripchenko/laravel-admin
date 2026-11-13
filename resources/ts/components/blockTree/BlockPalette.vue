<script setup lang="ts">
/**
 * The palette of the block tree: the types allowed at the chosen place and
 * the presets — ready blocks of those types, with their own data and meta —
 * grouped, with a search, a description and a thumbnail when they have them.
 * A pick inserts the block there.
 */
import { computed, nextTick, onMounted, ref } from 'vue'
import { UidButton, UidIcon, UidInput } from '@dskripchenko/ui'
import { Box, X } from 'lucide-vue-next'
import { resolveIcon } from '../shell/iconRegistry'
import { trSafe as tr } from '../../stores/i18n'
import { testIdOf } from '../../composables/testId'
import type { BlockPresetDef, BlockTypeDef } from './tree'

const props = withDefaults(defineProps<{
  types: BlockTypeDef[]
  /** The presets allowed at the place. */
  presets?: BlockPresetDef[]
  /** Where the block goes, for the heading: the parent's label, or null for the top level. */
  place: string | null
}>(), { presets: () => [] })

const emit = defineEmits<{ pick: [def: BlockTypeDef]; pickPreset: [preset: BlockPresetDef]; close: [] }>()

const query = ref('')
const root = ref<HTMLElement | null>(null)

type Item =
  | { kind: 'type'; key: string; label: string; description?: string | null; icon?: string | null; thumbnail?: string | null; def: BlockTypeDef }
  | { kind: 'preset'; key: string; label: string; description?: string | null; icon?: string | null; thumbnail?: string | null; preset: BlockPresetDef }

const items = computed<Array<Item & { group: string }>>(() => [
  ...props.types.map((def) => ({
    kind: 'type' as const,
    key: `type:${def.type}`,
    label: def.label,
    description: def.description,
    icon: def.icon,
    thumbnail: def.thumbnail,
    group: def.group ?? '',
    def,
  })),
  ...props.presets.map((preset) => ({
    kind: 'preset' as const,
    key: `preset:${preset.key}`,
    label: preset.label,
    description: preset.description,
    // A preset without its own icon shows its type's.
    icon: preset.icon ?? props.types.find((t) => t.type === preset.type)?.icon ?? null,
    thumbnail: preset.thumbnail,
    group: preset.group ?? '',
    preset,
  })),
])

const groups = computed(() => {
  const q = query.value.trim().toLocaleLowerCase()
  const found = items.value.filter(
    (t) => q === '' || t.label.toLocaleLowerCase().includes(q) || (t.description ?? '').toLocaleLowerCase().includes(q),
  )
  const byGroup = new Map<string, Item[]>()
  for (const t of found) byGroup.set(t.group, [...(byGroup.get(t.group) ?? []), t])
  return [...byGroup.entries()].map(([name, list]) => ({ name, list }))
})

function choose(item: Item): void {
  if (item.kind === 'type') emit('pick', item.def)
  else emit('pickPreset', item.preset)
}

function testIdOfItem(item: Item): string {
  return item.kind === 'type'
    ? testIdOf('block-tree-palette-type', item.def.type)
    : testIdOf('block-tree-palette-preset', item.preset.key)
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  void nextTick(() => root.value?.querySelector<HTMLInputElement>('input')?.focus())
})
</script>

<template>
  <div
    ref="root"
    class="admin-block-tree-palette"
    role="dialog"
    :aria-label="tr('Добавить блок')"
    data-testid="block-tree-palette"
    @keydown="onKey"
  >
    <div class="admin-block-tree-palette__head">
      <strong>{{ place ? `${tr('Добавить в')} «${place}»` : tr('Добавить блок') }}</strong>
      <UidButton size="sm" variant="ghost" :aria-label="tr('Закрыть')" data-testid="block-tree-palette-close" @click="emit('close')">
        <UidIcon :icon="X" :size="16" />
      </UidButton>
    </div>
    <UidInput v-model="query" size="sm" :placeholder="tr('Поиск по типам')" data-testid="block-tree-palette-search" />
    <div class="admin-block-tree-palette__list">
      <p v-if="groups.length === 0" class="admin-block-tree-palette__empty">{{ tr('Сюда нельзя добавить ни один тип') }}</p>
      <section v-for="g in groups" :key="g.name">
        <h4 v-if="g.name" class="admin-block-tree-palette__group">{{ g.name }}</h4>
        <button
          v-for="t in g.list"
          :key="t.key"
          type="button"
          class="admin-block-tree-palette__item"
          :class="{ 'is-preset': t.kind === 'preset' }"
          :data-type="t.kind === 'type' ? t.def.type : undefined"
          :data-preset="t.kind === 'preset' ? t.preset.key : undefined"
          :data-testid="testIdOfItem(t)"
          @click="choose(t)"
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
