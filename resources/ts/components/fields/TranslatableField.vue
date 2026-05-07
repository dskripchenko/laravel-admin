<script setup lang="ts">
/**
 * TranslatableField — input с табами по локалям для translatable-моделей.
 *
 * Backend laravel-translatable хранит переводы в pivot-таблице. Для admin
 * Resource сериализует значение поля как `{ru: '...', en: '...'}`. Этот
 * компонент рендерит таб'ы по локалям и редактирует каждое значение
 * отдельно.
 *
 * Локали:
 *   - resource manifest имеет `meta.locale` + `bootstrap.availableLocales`;
 *   - prop `locales` явно перебивает (для случаев когда модель ограничена
 *     подмножеством локалей).
 *
 * Backend payload — Object keyed by locale. Form-state сохраняет тот же
 * shape; bridge `TranslatableFieldBridge::extract()` извлекает на стороне
 * ResourceController при save.
 */
import { computed, ref } from 'vue'
import { UidInput } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { useLocaleStore } from '../../stores/locale'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  /** Override доступных локалей. Default — locale.available. */
  locales?: string[]
  /** Использовать textarea вместо input (для длинных полей body/description). */
  multiline?: boolean
  rows?: number
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  locales: undefined,
  multiline: false,
  rows: 3,
  disabled: false,
})

const form = useFormState()
const localeStore = useLocaleStore()

const availableLocales = computed<string[]>(
  () => props.locales ?? localeStore.available ?? [localeStore.current ?? 'ru'],
)
const activeLocale = ref<string>(localeStore.current ?? availableLocales.value[0] ?? 'ru')

const value = computed<Record<string, string>>(() => {
  const v = form.getField(props.name)
  if (v && typeof v === 'object' && !Array.isArray(v)) {
    return v as Record<string, string>
  }
  // Если поле пришло как plain-string (legacy non-translatable), оборачиваем.
  if (typeof v === 'string') return { [activeLocale.value]: v }
  return {}
})

function update(locale: string, next: string): void {
  const current = { ...value.value }
  current[locale] = next
  form.setField(props.name, current)
}

const errorMsg = computed<string | undefined>(() => {
  // Backend может возвращать ошибки per-locale (`field.ru` / `field.en`).
  const errors = form.errors[props.name]
  if (errors?.[0]) return errors[0]
  for (const loc of availableLocales.value) {
    const e = form.errors[`${props.name}.${loc}`]?.[0]
    if (e) return `[${loc}] ${e}`
  }
  return undefined
})

function isFilled(locale: string): boolean {
  const v = value.value[locale]
  return typeof v === 'string' && v.trim() !== ''
}
</script>

<template>
  <div :class="['admin-field', { 'admin-field--invalid': errorMsg !== undefined }]">
    <label v-if="label" class="admin-field__label">
      <span>{{ label }}</span>
      <span v-if="required" class="admin-field__required" aria-hidden="true">*</span>
    </label>
    <div class="admin-translatable">
      <div class="admin-translatable__tabs" role="tablist">
        <button
          v-for="loc in availableLocales"
          :key="loc"
          type="button"
          role="tab"
          :class="[
            'admin-translatable__tab',
            {
              'admin-translatable__tab--active': activeLocale === loc,
              'admin-translatable__tab--filled': isFilled(loc),
            },
          ]"
          :aria-selected="activeLocale === loc"
          @click="activeLocale = loc"
        >
          {{ loc.toUpperCase() }}
          <span v-if="isFilled(loc)" class="admin-translatable__dot" aria-hidden="true" />
        </button>
      </div>
      <div class="admin-translatable__panel">
        <UidInput
          v-if="!multiline"
          :model-value="value[activeLocale] ?? ''"
          :placeholder="placeholder ?? ''"
          :disabled="disabled"
          @update:model-value="(v) => update(activeLocale, v as string)"
        />
        <textarea
          v-else
          :value="value[activeLocale] ?? ''"
          :placeholder="placeholder ?? ''"
          :rows="rows"
          :disabled="disabled"
          class="admin-translatable__textarea"
          @input="(e) => update(activeLocale, (e.target as HTMLTextAreaElement).value)"
        />
      </div>
    </div>
    <p v-if="errorMsg" class="admin-field__error">{{ errorMsg }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>

<style>
.admin-translatable { display: flex; flex-direction: column; gap: 6px; }
.admin-translatable__tabs {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  align-self: flex-start;
  padding: 2px;
  background: var(--uid-surface-base);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
}
.admin-translatable__tab {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  font-size: 11px;
  font-weight: 600;
  color: var(--uid-text-secondary);
  cursor: pointer;
  letter-spacing: 0.04em;
}
.admin-translatable__tab:hover { color: var(--uid-text-primary); }
.admin-translatable__tab--active {
  background: var(--uid-surface-raised);
  color: var(--uid-text-primary);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.admin-translatable__dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--uid-color-success, #10b981);
}
.admin-translatable__textarea {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  color: var(--uid-text-primary);
  font: inherit;
  font-size: 13px;
  resize: vertical;
}
.admin-translatable__textarea:focus {
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
  border-color: var(--uid-accent);
}
</style>
