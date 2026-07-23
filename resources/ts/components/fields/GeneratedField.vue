<script setup lang="ts">
/**
 * GeneratedField — строка с автогенерацией криптослучайного значения
 * (токены, secret keys). На create-форме при пустом значении генерирует
 * при монтировании; кнопка «Сгенерировать» перегенерирует вручную.
 *
 * Генерация ТОЛЬКО через crypto.getRandomValues (без Math.random-fallback —
 * для секретов он недопустим); rejection sampling убирает modulo bias.
 * Если crypto недоступен (экзотика) — автогенерации нет, поле остаётся
 * обычным ручным вводом.
 */
import { computed, onMounted } from 'vue'
import { UidButton, UidInput } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { useI18nStore } from '../../stores/i18n'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  /** Длина генерируемой строки. */
  length?: number
  /** Алфавит генерации. */
  charset?: string
  /** Автогенерация при монтировании, если значение пусто. */
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
 * Криптослучайная строка без modulo bias: байт принимается только из
 * диапазона, кратного длине алфавита (rejection sampling).
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

function onUpdate(next: string): void {
  form.setField(props.name, next)
}

onMounted(() => {
  if (props.autogenerate && !props.disabled && value.value === '') generate()
})
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
        {{ i18n.has('admin.fields.generate') ? i18n.t('admin.fields.generate') : 'Сгенерировать' }}
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
