<script setup lang="ts">
/**
 * GeneratedField — a string that generates a cryptographically random value:
 * tokens, secret keys. On a create form it generates on mount when the value
 * is empty, and the "Generate" button regenerates it by hand.
 *
 * The generation goes through crypto.getRandomValues ONLY — no Math.random
 * fallback, which will not do for a secret — and rejection sampling removes
 * the modulo bias. Where crypto is unavailable, which would be exotic, nothing
 * is generated and the field stays an ordinary manual input.
 */
import { computed, ref, watch } from 'vue'
import { UidButton, UidInput } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { useI18nStore } from '../../stores/i18n'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  /** The generated string's length. */
  length?: number
  /** The alphabet it is drawn from. */
  charset?: string
  /** Whether to generate on mount when the value is empty. */
  autogenerate?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  placeholder: null,
  disabled: false,
  length: 32,
  charset: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
  autogenerate: true,
})

const form = useFormState()
const i18n = useI18nStore()
const value = computed<string>(() => (form.getField(props.name) as string | null | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const cryptoAvailable = typeof globalThis.crypto?.getRandomValues === 'function'

/**
 * A cryptographically random string free of modulo bias: a byte is accepted
 * only from the range that is a multiple of the alphabet's length — rejection
 * sampling.
 */
function randomString(length: number, charset: string): string {
  const n = charset.length
  const limit = Math.floor(256 / n) * n
  let out = ''
  const buf = new Uint8Array(length * 2)
  while (out.length < length) {
    globalThis.crypto.getRandomValues(buf)
    for (const byte of buf) {
      if (byte < limit) {
        out += charset[byte % n]
        if (out.length === length) break
      }
    }
  }
  return out
}

function generate(): void {
  if (!cryptoAvailable) return
  form.setField(props.name, randomString(props.length, props.charset))
}

const userEdited = ref(false)

function onUpdate(next: string): void {
  userEdited.value = true
  form.setField(props.name, next)
}

/*
 * The generation hangs off an immediate watch rather than onMounted: seeding
 * the create form, in prepareCreate, may overwrite the state AFTER the field
 * has mounted and wipe the generated value. A browser smoke test uncovered
 * that; jsdom, with its synchronous provideFormState, never caught the race.
 * An empty value that nobody typed into is generated again; one the user
 * cleared themselves is not.
 */
watch(
  value,
  (v) => {
    if (props.autogenerate && !props.disabled && !userEdited.value && v === '') generate()
  },
  { immediate: true },
)
</script>

<template>
  <div class="uid-form-field admin-generated" :class="{ 'uid-form-field--error': !!errorMsg }">
    <label v-if="label" class="uid-form-field__label">
      {{ label }}<span v-if="required" class="uid-form-field__required" aria-hidden="true">*</span>
    </label>
    <div class="admin-generated__row">
      <UidInput
        :model-value="value"
        :error="errorMsg"
        :placeholder="placeholder ?? undefined"
        :required="required"
        :disabled="disabled"
        :name="name"
        class="admin-generated__input"
        @update:model-value="onUpdate"
      />
      <UidButton
        v-if="cryptoAvailable"
        variant="secondary"
        size="md"
        type="button"
        :disabled="disabled"
        @click="generate"
      >
        {{ i18n.has('admin.fields.generate') ? i18n.t('admin.fields.generate') : tr('Сгенерировать') }}
      </UidButton>
    </div>
    <p v-if="errorMsg" class="uid-form-field__hint uid-form-field__hint--error">{{ errorMsg }}</p>
    <p v-else-if="help" class="uid-form-field__hint">{{ help }}</p>
  </div>
</template>

<style>
.admin-generated__row {
  display: flex;
  gap: var(--uid-space-sm, 8px);
  align-items: flex-start;
}
.admin-generated__input {
  flex: 1;
}
</style>
