<script setup lang="ts">
/**
 * FieldRenderer резолвит конкретный field-компонент по `node.type` из реестра
 * и forwards остальные props через v-bind.
 *
 * Узлы манифеста имеют форму:
 *   { type: 'text', name: 'title', label: 'Заголовок', required: true, ... }
 *
 * Field хранит value через provide/inject form-state — узлу не передаётся
 * `modelValue` напрямую. Это позволяет строить произвольно-глубокие layout'ы
 * без явного proppin'га state'а.
 *
 * Conditional visibility: если `node.reactive = {fieldName: expected}` — поле
 * скрывается пока другое поле формы не совпадёт с `expected` (или с одним
 * из элементов list). Соответствие — `===`.
 */
import { computed } from 'vue'
import { getField } from './registry'
import { tryUseFormState } from './formState'
import UnknownField from '../fields/UnknownField.vue'

export interface FieldNode extends Record<string, unknown> {
  type: string
  name: string
  reactive?: Record<string, unknown>
  visibility?: { create?: boolean; update?: boolean; view?: boolean }
}

interface Props {
  node: FieldNode
}

const props = defineProps<Props>()
const component = computed(() => getField(props.node.type))

// Form-state может отсутствовать (если FieldRenderer используется вне формы,
// например, в Repeater'е c локальным state'ом). В таком случае visibility
// всегда true — reactive не имеет смысла.
const form = tryUseFormState()

// Контекстная видимость: backend Field::onCreate(false)/onUpdate(false)
// сериализуется в node.visibility — скрываем поле, если оно не предназначено
// для текущего режима формы (mode отсутствует = рендерим всё, BC).
const isContextVisible = computed<boolean>(() => {
  const mode = form?.mode
  if (!mode) return true
  return props.node.visibility?.[mode] !== false
})

const isReactiveVisible = computed<boolean>(() => {
  const reactive = props.node.reactive
  if (!reactive || typeof reactive !== 'object' || !form) return true

  for (const [fieldName, expected] of Object.entries(reactive)) {
    const actual = form.getField(fieldName)
    if (Array.isArray(expected)) {
      if (!expected.includes(actual as never)) return false
    } else if (actual !== expected) {
      return false
    }
  }
  return true
})

const fieldProps = computed(() => {
  // Backend Field::toArray() кладёт type-specific опции в `attributes`
  // (suggestions, options, multiple, currency и т.п.). Разворачиваем их
  // на верхний уровень — Field-компоненты ожидают props без обёртки.
  const { type: _type, attributes, ...rest } = props.node
  const attrs = (attributes as Record<string, unknown> | undefined) ?? {}
  return { ...rest, ...attrs }
})
</script>

<template>
  <template v-if="isContextVisible && isReactiveVisible">
    <component :is="component" v-if="component" v-bind="fieldProps" />
    <UnknownField v-else :type="node.type" :name="node.name" />
  </template>
</template>
