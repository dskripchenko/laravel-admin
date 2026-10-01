<script setup lang="ts">
/**
 * TreeSelectField — the backend's Field\TreeSelect over UidTreeSelect.
 *
 * The tree is `[{value, label, children}]`, from `->tree([...])` or built by
 * the backend out of `->fromModel(...)`. The state is one value, or a list
 * with `->multiple()` (or `->checkable()`, which implies several values).
 * `->selectableParents(false)` leaves only the leaves selectable.
 */
import { computed } from 'vue'
import { UidTreeSelect } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { normalizeTree, type OptionTreeItem, type OptionValue } from './support/tree'

interface TreeNode {
  key: OptionValue
  label: string
  disabled?: boolean
  selectable?: boolean
  children?: TreeNode[]
}

interface Props {
  name: string
  tree?: unknown
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  multiple?: boolean
  checkable?: boolean
  selectableParents?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  tree: () => [],
  label: null,
  help: null,
  required: false,
  placeholder: null,
  disabled: false,
  readonly: false,
  multiple: false,
  checkable: false,
  selectableParents: true,
})

const isMultiple = computed<boolean>(() => props.multiple || props.checkable)

function toNodes(items: OptionTreeItem[]): TreeNode[] {
  return items.map((item) => {
    const node: TreeNode = { key: item.value, label: item.label }
    if (item.disabled) node.disabled = true
    if (item.children) {
      node.children = toNodes(item.children)
      if (!props.selectableParents) node.selectable = false
    }
    return node
  })
}

const items = computed<OptionTreeItem[]>(() => normalizeTree(props.tree))
const nodes = computed<TreeNode[]>(() => toNodes(items.value))

/** Every key of the tree by its string form, so '5' from the state finds 5. */
const keyIndex = computed<Map<string, OptionValue>>(() => {
  const map = new Map<string, OptionValue>()
  const walk = (list: OptionTreeItem[]): void => {
    for (const n of list) {
      map.set(String(n.value), n.value)
      if (n.children) walk(n.children)
    }
  }
  walk(items.value)
  return map
})
const canon = (v: unknown): OptionValue => keyIndex.value.get(String(v)) ?? (v as OptionValue)

const form = useFormState()
const value = computed<OptionValue | OptionValue[] | null>(() => {
  const v = form.getField(props.name)
  if (isMultiple.value) {
    if (Array.isArray(v)) return v.map(canon)
    return v === null || v === undefined || v === '' ? [] : [canon(v)]
  }
  if (Array.isArray(v)) return v.length > 0 ? canon(v[0]) : null
  return v === null || v === undefined || v === '' ? null : canon(v)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: OptionValue | OptionValue[] | null): void {
  if (props.readonly) return
  form.setField(props.name, next)
}
</script>

<template>
  <UidTreeSelect
    :model-value="value"
    :nodes="nodes"
    :multiple="isMultiple"
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :placeholder="placeholder ?? undefined"
    :disabled="disabled || readonly"
    :clearable="!required"
    @update:model-value="onUpdate"
  />
</template>
