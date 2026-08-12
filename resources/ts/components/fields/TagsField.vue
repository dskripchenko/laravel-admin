<script setup lang="ts">
/**
 * TagsField — an input for a list<string>, with chips and free-form entry.
 *
 * The backend field class is Dskripchenko\LaravelAdmin\Field\TagsInput with
 * fieldType()='tags'; the frontend's builtin registry maps 'tags' here.
 *
 * What it does:
 *   - Free input: Enter or a separator (`,` or ';') adds a new chip even when
 *     it is not among the suggestions — which matters for wildcards
 *     ('admin.content.*') and other arbitrary keys.
 *   - Suggestions: the backend puts `attributes.suggestions: string[]` there;
 *     when it does, a dropdown filtered by the typed query is rendered.
 *   - Backspace on an empty input removes the last chip.
 *   - Clicking a chip's × removes it.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import { UidIcon, usePopover } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { trSafe as tr } from '../../stores/i18n'

interface SuggestionGroup {
  label: string
  items: string[]
}

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  /** A flat list of suggestions, kept for compatibility. */
  suggestions?: string[]
  /**
   * Grouped suggestions. When they are set, the dropdown is rendered with
   * group headings; they take precedence over `suggestions`.
   */
  suggestionsByGroup?: SuggestionGroup[]
  /** The maximum number of tags. */
  maxItems?: number
  /**
   * An additional separator besides Enter, such as `,` or `;`. Typing one of
   * these turns whatever precedes it into a chip.
   */
  separator?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: tr('Введите значение и нажмите Enter…'),
  required: false,
  suggestions: () => [],
  suggestionsByGroup: () => [],
  maxItems: 0,
  separator: ',',
  disabled: false,
})

const form = useFormState()

const tags = computed<string[]>(() => {
  const v = form.getField(props.name)
  if (Array.isArray(v)) return v.map((x) => String(x))
  if (typeof v === 'string' && v !== '') return v.split(',').map((s) => s.trim()).filter(Boolean)
  return []
})

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const query = ref<string>('')
const focused = ref<boolean>(false)
const inputRef = ref<HTMLInputElement | null>(null)
const containerRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)

// The dropdown is teleported into the body and positioned through usePopover:
// otherwise any ancestor with overflow: hidden clips it — visible in the
// forms, where the right edge of the suggestions runs off the card.
const { floatingStyle, update: updatePopover } = usePopover(containerRef, dropdownRef, {
  placement: 'bottom-start',
  offset: 4,
})

const containerWidth = ref<number>(0)
function syncContainerWidth(): void {
  containerWidth.value = containerRef.value?.getBoundingClientRect().width ?? 0
}

const dropdownStyle = computed(() => ({
  ...floatingStyle.value,
  // The dropdown's width follows the chip input's — standard combobox behaviour.
  minWidth: containerWidth.value > 0 ? `${containerWidth.value}px` : 'auto',
}))

function setTags(next: string[]): void {
  // Deduplicate and apply the limit.
  const seen = new Set<string>()
  const out: string[] = []
  for (const t of next) {
    const trimmed = t.trim()
    if (trimmed === '' || seen.has(trimmed)) continue
    seen.add(trimmed)
    out.push(trimmed)
    if (props.maxItems > 0 && out.length >= props.maxItems) break
  }
  form.setField(props.name, out)
}

function addTag(value: string): void {
  const v = value.trim()
  if (v === '') return
  if (tags.value.includes(v)) {
    query.value = ''
    return
  }
  setTags([...tags.value, v])
  query.value = ''
}

function removeTag(idx: number): void {
  const next = [...tags.value]
  next.splice(idx, 1)
  setTags(next)
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter') {
    e.preventDefault()
    if (activeIdx.value >= 0 && filteredFlat.value[activeIdx.value]) {
      addTag(filteredFlat.value[activeIdx.value])
    } else if (query.value.trim() !== '') {
      addTag(query.value)
    }
  } else if (e.key === 'Backspace' && query.value === '' && tags.value.length > 0) {
    removeTag(tags.value.length - 1)
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIdx.value = Math.min(activeIdx.value + 1, filteredFlat.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIdx.value = Math.max(activeIdx.value - 1, -1)
  } else if (e.key === 'Escape') {
    focused.value = false
    inputRef.value?.blur()
  }
}

function onInput(e: Event): void {
  const v = (e.target as HTMLInputElement).value
  // Handling the separator character: cut at it and commit what came before.
  if (props.separator && v.includes(props.separator)) {
    const parts = v.split(props.separator)
    const completed = parts.slice(0, -1).map((p) => p.trim()).filter(Boolean)
    completed.forEach(addTag)
    query.value = parts[parts.length - 1] ?? ''
    return
  }
  query.value = v
}

const activeIdx = ref<number>(-1)

/**
 * When the backend passed suggestionsByGroup we render group headings,
 * otherwise a flat list. Either way we build one filtered array so that arrow
 * navigation can work by index — activeIdx points into `filteredFlat`.
 */
const grouped = computed<boolean>(
  () => Array.isArray(props.suggestionsByGroup) && props.suggestionsByGroup.length > 0,
)

const filteredGroups = computed<SuggestionGroup[]>(() => {
  const q = query.value.trim().toLowerCase()
  const used = new Set(tags.value)
  if (!grouped.value) return []
  return props.suggestionsByGroup
    .map((g) => ({
      label: g.label,
      items: g.items.filter(
        (s) => !used.has(s) && (q === '' || s.toLowerCase().includes(q)),
      ),
    }))
    .filter((g) => g.items.length > 0)
})

const filteredFlat = computed<string[]>(() => {
  if (grouped.value) {
    return filteredGroups.value.flatMap((g) => g.items)
  }
  const q = query.value.trim().toLowerCase()
  const used = new Set(tags.value)
  return props.suggestions
    .filter((s) => !used.has(s))
    .filter((s) => q === '' || s.toLowerCase().includes(q))
    .slice(0, 200)
})

/** How many group headings sit above the current item, to shift the index lookup. */
function isItemActive(globalIdx: number): boolean {
  return globalIdx === activeIdx.value
}

watch(query, () => {
  activeIdx.value = -1
})

function onContainerClick(): void {
  if (!props.disabled) inputRef.value?.focus()
}

function onSelectSuggestion(s: string): void {
  addTag(s)
  nextTick(() => inputRef.value?.focus())
}

function onBlur(): void {
  // A delay, so that a click on the dropdown still lands
  window.setTimeout(() => {
    focused.value = false
    if (query.value.trim() !== '') {
      addTag(query.value)
    }
  }, 120)
}

// When the dropdown opens — focus plus some suggestions — we recompute the
// position and subscribe to scroll and resize; on close we unsubscribe.
watch(
  () => focused.value && filteredFlat.value.length > 0,
  async (isOpen) => {
    if (isOpen) {
      syncContainerWidth()
      await nextTick()
      updatePopover()
      requestAnimationFrame(() => updatePopover())
      window.addEventListener('resize', updatePopover)
      window.addEventListener('scroll', updatePopover, true)
    } else {
      window.removeEventListener('resize', updatePopover)
      window.removeEventListener('scroll', updatePopover, true)
    }
  },
)
</script>

<template>
  <div :class="['admin-field', { 'admin-field--invalid': errorMsg !== undefined }]">
    <label v-if="label" class="admin-field__label">
      <span>{{ label }}</span>
      <span v-if="required" class="admin-field__required" aria-hidden="true">*</span>
    </label>
    <div
      ref="containerRef"
      :class="[
        'admin-field__control admin-tags',
        {
          'admin-tags--focused': focused,
          'admin-tags--disabled': disabled,
        },
      ]"
      @click="onContainerClick"
    >
      <span
        v-for="(tag, idx) in tags"
        :key="`${tag}-${idx}`"
        class="admin-tags__chip"
      >
        <span class="admin-tags__chip-text">{{ tag }}</span>
        <button
          v-if="!disabled"
          type="button"
          class="admin-tags__chip-remove"
          :aria-label="tr('Удалить')"
          @click.stop="removeTag(idx)"
        >
          <UidIcon :icon="X" :size="10" />
        </button>
      </span>
      <input
        ref="inputRef"
        :value="query"
        :placeholder="tags.length === 0 ? tr(placeholder ?? '') : ''"
        :disabled="disabled"
        class="admin-tags__input"
        type="text"
        @input="onInput"
        @keydown="onKeydown"
        @focus="focused = true"
        @blur="onBlur"
      />

      <Teleport to="body">
      <div
        v-if="focused && filteredFlat.length > 0"
        ref="dropdownRef"
        class="admin-tags__dropdown"
        role="listbox"
        :style="dropdownStyle"
      >
        <!-- Grouped рендер: заголовок группы + items внутри -->
        <template v-if="grouped">
          <template
            v-for="(g, gi) in filteredGroups"
            :key="`g-${gi}-${g.label}`"
          >
            <div class="admin-tags__group-label">{{ g.label }}</div>
            <button
              v-for="s in g.items"
              :key="s"
              type="button"
              role="option"
              :class="[
                'admin-tags__option',
                {
                  'admin-tags__option--active': isItemActive(
                    filteredFlat.indexOf(s),
                  ),
                },
              ]"
              :aria-selected="isItemActive(filteredFlat.indexOf(s))"
              @mousedown.prevent="onSelectSuggestion(s)"
              @mouseenter="activeIdx = filteredFlat.indexOf(s)"
            >
              {{ s }}
            </button>
          </template>
        </template>

        <!-- Flat рендер (без групп) — обратная совместимость -->
        <template v-else>
          <button
            v-for="(s, idx) in filteredFlat"
            :key="s"
            type="button"
            role="option"
            :class="['admin-tags__option', { 'admin-tags__option--active': idx === activeIdx }]"
            :aria-selected="idx === activeIdx"
            @mousedown.prevent="onSelectSuggestion(s)"
            @mouseenter="activeIdx = idx"
          >
            {{ s }}
          </button>
        </template>
      </div>
      </Teleport>
    </div>
    <p v-if="errorMsg" class="admin-field__error">{{ errorMsg }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>

<style>
.admin-tags {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  min-height: 36px;
  padding: 4px 8px;
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  cursor: text;
}
.admin-tags--focused {
  border-color: var(--uid-accent);
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
}
.admin-tags--disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.admin-tags__chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 6px 2px 8px;
  background: color-mix(in srgb, var(--uid-accent) 14%, transparent);
  color: var(--uid-accent);
  border-radius: var(--uid-radius-sm);
  font-size: 12px;
  font-weight: 500;
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
}
.admin-tags__chip-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border: 0;
  background: transparent;
  color: inherit;
  border-radius: 50%;
  cursor: pointer;
}
.admin-tags__chip-remove:hover {
  background: color-mix(in srgb, var(--uid-accent) 28%, transparent);
}
.admin-tags__input {
  flex: 1;
  min-width: 80px;
  height: 24px;
  padding: 0 4px;
  border: 0;
  background: transparent;
  outline: none;
  font: inherit;
  font-size: 13px;
  color: var(--uid-text-primary);
}
.admin-tags__dropdown {
  /* The position comes from usePopover, since this is teleported into the body — top/left/transform
     приходят inline через :style. Здесь только визуальный стиль. */
  z-index: var(--uid-z-popover, 1000);
  margin: 0;
  padding: 4px;
  display: flex;
  flex-direction: column;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  box-shadow: var(--uid-shadow-md);
  max-height: 320px;
  overflow-y: auto;
}
.admin-tags__group-label {
  padding: 8px 10px 4px;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--uid-text-tertiary);
  font-weight: var(--uid-font-weight-semibold);
  position: sticky;
  top: 0;
  background: var(--uid-surface-raised);
  z-index: 1;
}
.admin-tags__group-label:first-child { padding-top: 4px; }
.admin-tags__option {
  display: block;
  width: 100%;
  text-align: left;
  padding: 6px 10px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  font-size: 13px;
  cursor: pointer;
  color: var(--uid-text-primary);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
}
.admin-tags__option:hover,
.admin-tags__option--active {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
}
</style>
