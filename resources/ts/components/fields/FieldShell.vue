<script setup lang="ts">
/**
 * Обёртка вокруг любого field-компонента: рендерит label, required-маркер,
 * help-текст и error-сообщения. Сам контрол идёт в default-slot.
 *
 * Использование:
 *
 *     <FieldShell :name="name" :label="label" :required="required" :help="help">
 *       <input v-model="value" :id="id" />
 *     </FieldShell>
 */
import { computed } from 'vue'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /** ID для <label for="..."> и контрола внутри. Авто-генерим если null. */
  controlId?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  controlId: null,
})

const form = useFormState()
const errorMessages = computed<string[]>(() => form.errors[props.name] ?? [])
const hasError = computed(() => errorMessages.value.length > 0)

const inputId = computed(() => props.controlId ?? `admin-field-${props.name}`)
</script>

<template>
  <div :class="['admin-field', { 'admin-field--invalid': hasError }]">
    <label v-if="label" :for="inputId" class="admin-field__label">
      <span>{{ label }}</span>
      <span v-if="required" class="admin-field__required" aria-hidden="true">*</span>
    </label>
    <div class="admin-field__control">
      <slot :id="inputId" :invalid="hasError" />
    </div>
    <p v-if="hasError" class="admin-field__error">{{ errorMessages[0] }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>

<style>
.admin-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 12px;
}
.admin-field__label {
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text, #111827);
  display: inline-flex;
  gap: 4px;
}
.admin-field__required {
  color: var(--admin-danger, #ef4444);
}
.admin-field__help {
  font-size: 12px;
  color: var(--admin-muted, #6b7280);
  margin: 0;
}
.admin-field__error {
  font-size: 12px;
  color: var(--admin-danger, #ef4444);
  margin: 0;
}
.admin-field--invalid input,
.admin-field--invalid textarea,
.admin-field--invalid select {
  border-color: var(--admin-danger, #ef4444) !important;
}
</style>
