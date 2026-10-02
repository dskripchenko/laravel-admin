<script setup lang="ts">
/**
 * SlugField — the backend's Field\Slug: a UidInput that fills itself from
 * another field of the form (`from`), through slugify(), the port of
 * Slug::generate().
 *
 * It follows the source until the slug is edited by hand; clearing the slug
 * hands it back to the source. A saved record's slug that does not match its
 * source counts as edited, so opening a form never rewrites it.
 *
 * `follow: false` (Slug::reactive(false)) fills only an empty slug: a new
 * record's slug follows the source until it is edited, a saved one is never
 * rewritten.
 */
import { computed, ref, watch } from 'vue'
import { UidInput } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { slugify } from './support/slugify'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  /** The source field's name. */
  from?: string | null
  separator?: string | null
  /** Slug::reactive(): follow every change of the source; true by default. */
  follow?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  readonly: false,
  from: null,
  separator: '-',
  follow: true,
})

const form = useFormState()
const value = computed<string>(() => {
  const v = form.getField(props.name)
  return v === null || v === undefined ? '' : String(v)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const separator = computed<string>(() => props.separator ?? '-')

/**
 * The source's text. A translatable source holds an object of locales; its
 * first filled value is taken.
 */
function sourceText(): string {
  if (!props.from) return ''
  const raw = form.getField(props.from)
  if (raw === null || raw === undefined) return ''
  if (typeof raw === 'object') {
    const first = Object.values(raw as Record<string, unknown>).find(
      (v) => typeof v === 'string' && v.trim() !== '',
    )
    return typeof first === 'string' ? first : ''
  }
  return String(raw)
}

/**
 * Whether the slug still follows its source. An empty slug always does; a
 * filled one does when it is what the source gives — and, without `follow`,
 * only when it was not there when the form opened.
 */
const following = ref<boolean>(
  value.value === ''
    || (props.follow && value.value === slugify(sourceText(), separator.value)),
)

watch(
  () => sourceText(),
  (text) => {
    if (!following.value || props.disabled || props.readonly) return
    const next = slugify(text, separator.value)
    if (next !== value.value) form.setField(props.name, next)
  },
)

function onUpdate(next: string): void {
  form.setField(props.name, next)
  // Typing takes the slug over; clearing it hands it back to the source.
  following.value = next === ''
}
</script>

<template>
  <UidInput
    :model-value="value"
    type="text"
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :placeholder="placeholder ?? undefined"
    :required="required"
    :disabled="disabled"
    :readonly="readonly"
    :name="name"
    autocomplete="off"
    @update:model-value="onUpdate"
  />
</template>
