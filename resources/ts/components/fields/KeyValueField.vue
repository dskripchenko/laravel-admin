<script setup lang="ts">
/**
 * KeyValueField — редактор Record<string, string> (backend Field\KeyValue,
 * fieldType 'key_value'). Хранит объект; UI — строки «ключ / значение»
 * с добавлением и удалением. `allowedKeys` превращает key-инпут в datalist.
 */
import { computed, ref, watch } from 'vue'
import { Plus, X } from 'lucide-vue-next'
import { UidButton, UidIcon, UidInput } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  keyLabel?: string
  valueLabel?: string
  addable?: boolean
  removable?: boolean
  allowedKeys?: string[]
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  keyLabel: 'Ключ',
  valueLabel: 'Значение',
  addable: true,
  removable: true,
  allowedKeys: () => [],
})

const form = useFormState()

interface Pair {
  key: string
  value: string
}

function fromState(): Pair[] {
  const v = form.getField(props.name)
  if (v === null || v === undefined || typeof v !== 'object' || Array.isArray(v)) return []
  return Object.entries(v as Record<string, unknown>).map(([key, value]) => ({
    key,
    value: value === null || value === undefined ? '' : String(value),
  }))
}

const pairs = ref<Pair[]>(fromState())

// Внешние изменения state (load записи) → пересобираем строки.
watch(
  () => form.getField(props.name),
  () => {
    const next = fromState()
    const current = JSON.stringify(Object.fromEntries(pairs.value.map((p) => [p.key, p.value])))
    if (JSON.stringify(Object.fromEntries(next.map((p) => [p.key, p.value]))) !== current) {
      pairs.value = next
    }
  },
)

function sync(): void {
  const out: Record<string, string> = {}
  for (const p of pairs.value) {
    if (p.key.trim() !== '') out[p.key.trim()] = p.value
  }
  form.setField(props.name, out)
}

function addPair(): void {
  pairs.value.push({ key: '', value: '' })
}

function removePair(idx: number): void {
  pairs.value.splice(idx, 1)
  sync()
}

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const listId = computed(() => `kv-keys-${props.name}`)
</script>

<template>
  <div class="uid-form-field admin-keyvalue" :class="{ 'uid-form-field--error': !!errorMsg }">
    <label v-if="label" class="uid-form-field__label">
      {{ label }}<span v-if="required" class="uid-form-field__required" aria-hidden="true">*</span>
    </label>

    <div class="admin-keyvalue__rows">
      <div class="admin-keyvalue__head">
        <span>{{ keyLabel }}</span>
        <span>{{ valueLabel }}</span>
        <span />
      </div>
      <div v-for="(p, idx) in pairs" :key="idx" class="admin-keyvalue__row">
        <UidInput
          v-model="p.key"
          :list="allowedKeys.length > 0 ? listId : undefined"
          :placeholder="keyLabel"
          @blur="sync"
        />
        <UidInput v-model="p.value" :placeholder="valueLabel" @blur="sync" />
        <UidButton
          v-if="removable"
          variant="ghost"
          size="sm"
          :aria-label="`Удалить ${p.key}`"
          @click="removePair(idx)"
        >
          <UidIcon :icon="X" :size="14" />
        </UidButton>
      </div>
      <datalist v-if="allowedKeys.length > 0" :id="listId">
        <option v-for="k in allowedKeys" :key="k" :value="k" />
      </datalist>
    </div>

    <UidButton v-if="addable" variant="secondary" size="sm" class="admin-keyvalue__add" @click="addPair">
      <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
      Добавить
    </UidButton>

    <p v-if="errorMsg" class="uid-form-field__hint uid-form-field__hint--error">{{ errorMsg }}</p>
    <p v-else-if="help" class="uid-form-field__hint">{{ help }}</p>
  </div>
</template>

<style scoped>
.admin-keyvalue__rows {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs, 6px);
}
.admin-keyvalue__head,
.admin-keyvalue__row {
  display: grid;
  grid-template-columns: 1fr 1fr 36px;
  gap: var(--uid-space-sm, 8px);
  align-items: center;
}
.admin-keyvalue__head {
  font-size: 12px;
  color: var(--uid-color-text-subtle, #6b7280);
}
.admin-keyvalue__add {
  margin-top: var(--uid-space-sm, 8px);
  align-self: flex-start;
}
</style>
