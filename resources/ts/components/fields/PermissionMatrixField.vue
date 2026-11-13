<script setup lang="ts">
/**
 * PermissionMatrixField — the backend's Field\PermissionMatrix: the
 * permissions of a role as a matrix.
 *
 * Groups are rows that fold, each with a three-state checkbox and «N из M»;
 * the toolbar holds the search over labels and keys, «Только выбранные»,
 * «Выбрать всё» (three states too) and «Выбрано N из M». The value is the
 * list of the checked keys, the second column's (`extraLabel`) among them.
 */
import { computed, ref } from 'vue'
import { ChevronDown, ChevronRight, Search } from 'lucide-vue-next'
import { UidCheckbox, UidFormField, UidIcon, UidInput, UidSwitch } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { testIdOf } from '../../composables/testId'
import { trSafe as tr, tRaw } from '../../stores/i18n'
import { countIn, filterGroups, keysOf, stateOf, toggleKeys, type MatrixGroup } from './permissionMatrix/matrix'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  groups?: MatrixGroup[] | null
  /** The caption of the second column (PermissionMatrix::extraColumn()). */
  extraLabel?: string | null
  /** Every group open from the start. */
  expanded?: boolean | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  groups: null,
  extraLabel: null,
  expanded: null,
})

const form = useFormState()
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const locked = computed<boolean>(() => props.disabled || props.readonly)
const allGroups = computed<MatrixGroup[]>(() => props.groups ?? [])

/** The checked keys as the state holds them: a list, a JSON list, or Orchid's `{key: 1}` map. */
const selectedList = computed<string[]>(() => {
  let v: unknown = form.getField(props.name)
  if (typeof v === 'string' && v.trim().startsWith('[')) {
    try { v = JSON.parse(v) } catch { v = [] }
  }
  if (Array.isArray(v)) return v.filter((x): x is string => typeof x === 'string')
  if (v && typeof v === 'object') {
    return Object.entries(v as Record<string, unknown>).filter(([, on]) => Boolean(on) && on !== '0').map(([k]) => k)
  }
  return []
})
const selected = computed<Set<string>>(() => new Set(selectedList.value))

const query = ref('')
const onlySelected = ref(false)

const visibleGroups = computed<MatrixGroup[]>(() => filterGroups(allGroups.value, query.value, onlySelected.value, selected.value))
const searching = computed<boolean>(() => query.value.trim() !== '' || onlySelected.value)

const allKeys = computed<string[]>(() => allGroups.value.flatMap((g) => keysOf(g.items, false)))
const totalState = computed(() => stateOf(allKeys.value, selected.value))
const totalCount = computed<number>(() => countIn(allKeys.value, selected.value))

const open = ref<Set<string>>(new Set(
  (props.expanded ?? allGroups.value.length <= 5) ? allGroups.value.map((g) => g.label) : [],
))
function isOpen(label: string): boolean {
  return searching.value || open.value.has(label)
}
function toggleOpen(label: string): void {
  const next = new Set(open.value)
  if (next.has(label)) next.delete(label)
  else next.add(label)
  open.value = next
}

/** The group as declared — its «N из M» counts every row, not the found ones. */
function fullGroup(label: string): MatrixGroup | undefined {
  return allGroups.value.find((g) => g.label === label)
}
function groupKeys(label: string): string[] {
  return keysOf(fullGroup(label)?.items ?? [], false)
}

function write(next: string[]): void {
  if (locked.value) return
  form.setField(props.name, next)
}

function setKeys(keys: string[], on: boolean): void {
  write(toggleKeys(selectedList.value, keys, on))
}

/** A group's checkbox works on the rows the search shows. */
function onGroup(group: MatrixGroup, on: boolean): void {
  setKeys(keysOf(group.items, !on), on)
}
function onAll(on: boolean): void {
  const groups = searching.value ? visibleGroups.value : allGroups.value
  setKeys(groups.flatMap((g) => keysOf(g.items, !on)), on)
}
function onItem(key: string, on: boolean, extra?: string | null): void {
  // Taking the main key away takes its extra one too: the extra is a widening of it.
  setKeys(!on && extra ? [key, extra] : [key], on)
}
function onExtra(key: string, extra: string, on: boolean): void {
  setKeys(on ? [key, extra] : [extra], on)
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="locked"
  >
    <div class="admin-permission-matrix" :data-testid="testIdOf('permission-matrix', name)">
      <div class="admin-permission-matrix__toolbar">
        <UidInput
          v-model="query"
          size="sm"
          :placeholder="tr('Найти право или ключ')"
          class="admin-permission-matrix__search"
          data-testid="permission-matrix-search"
        >
          <template #prepend><UidIcon :icon="Search" :size="14" /></template>
        </UidInput>
        <UidSwitch v-model="onlySelected" size="sm" :label="tr('Только выбранные')" data-testid="permission-matrix-only-selected" />
        <span class="admin-permission-matrix__spacer" />
        <span class="admin-permission-matrix__total" data-testid="permission-matrix-total">
          {{ tRaw('Выбрано :n из :m', { n: totalCount, m: allKeys.length }) }}
        </span>
        <UidCheckbox
          :model-value="totalState === 'all'"
          :indeterminate="totalState === 'some'"
          :disabled="locked || allKeys.length === 0"
          :label="tr('Выбрать всё')"
          data-testid="permission-matrix-all"
          @update:model-value="onAll"
        />
      </div>

      <table class="admin-permission-matrix__table">
        <thead>
          <tr>
            <th class="admin-permission-matrix__col-label">{{ tr('Раздел') }}</th>
            <th class="admin-permission-matrix__col-key">{{ tr('Ключ') }}</th>
            <th class="admin-permission-matrix__col-check">{{ tr('Доступ') }}</th>
            <th v-if="extraLabel" class="admin-permission-matrix__col-check">{{ extraLabel }}</th>
          </tr>
        </thead>
        <tbody v-for="group in visibleGroups" :key="group.label" :data-testid="testIdOf('permission-group', group.label)">
          <tr class="admin-permission-matrix__group">
            <td>
              <button
                type="button"
                class="admin-permission-matrix__fold"
                :aria-expanded="isOpen(group.label)"
                data-testid="permission-group-toggle"
                @click="toggleOpen(group.label)"
              >
                <UidIcon :icon="isOpen(group.label) ? ChevronDown : ChevronRight" :size="14" />
                <span class="admin-permission-matrix__group-label">{{ group.label }}</span>
              </button>
            </td>
            <td class="admin-permission-matrix__count" data-testid="permission-group-count">
              {{ tRaw(':n из :m', { n: countIn(groupKeys(group.label), selected), m: groupKeys(group.label).length }) }}
            </td>
            <td class="admin-permission-matrix__col-check">
              <UidCheckbox
                :model-value="stateOf(keysOf(group.items, false), selected) === 'all'"
                :indeterminate="stateOf(keysOf(group.items, false), selected) === 'some'"
                :disabled="locked"
                data-testid="permission-group-check"
                @update:model-value="(on: boolean) => onGroup(group, on)"
              />
            </td>
            <td v-if="extraLabel" />
          </tr>
          <template v-if="isOpen(group.label)">
            <tr v-for="item in group.items" :key="item.key" class="admin-permission-matrix__item" :data-testid="testIdOf('permission', item.key)">
              <td class="admin-permission-matrix__item-label">
                {{ item.label }}
                <div v-if="item.hint" class="admin-permission-matrix__hint">{{ item.hint }}</div>
              </td>
              <td><code class="admin-permission-matrix__key">{{ item.key }}</code></td>
              <td class="admin-permission-matrix__col-check">
                <UidCheckbox
                  :model-value="selected.has(item.key)"
                  :disabled="locked"
                  data-testid="permission-check"
                  @update:model-value="(on: boolean) => onItem(item.key, on, item.extra)"
                />
              </td>
              <td v-if="extraLabel" class="admin-permission-matrix__col-check">
                <UidCheckbox
                  v-if="item.extra"
                  :model-value="selected.has(item.extra)"
                  :disabled="locked"
                  data-testid="permission-extra"
                  @update:model-value="(on: boolean) => onExtra(item.key, item.extra!, on)"
                />
                <span v-else class="admin-permission-matrix__none">—</span>
              </td>
            </tr>
          </template>
        </tbody>
        <tbody v-if="visibleGroups.length === 0">
          <tr>
            <td :colspan="extraLabel ? 4 : 3" class="admin-permission-matrix__empty" data-testid="permission-matrix-empty">
              {{ searching ? tr('Ничего не найдено') : tr('Прав нет') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </UidFormField>
</template>

<style>
.admin-permission-matrix {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm, 8px);
  min-width: 0;
}
.admin-permission-matrix__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--uid-space-md, 12px);
}
.admin-permission-matrix__search {
  width: 260px;
  max-width: 100%;
}
.admin-permission-matrix__spacer {
  flex: 1;
}
.admin-permission-matrix__total {
  font-size: var(--uid-font-size-sm, 13px);
  color: var(--uid-text-secondary);
}
.admin-permission-matrix__table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--uid-font-size-sm, 13px);
}
.admin-permission-matrix__table th {
  text-align: left;
  font-weight: var(--uid-font-weight-medium, 500);
  color: var(--uid-text-secondary);
  padding: 6px 8px;
  border-bottom: 1px solid var(--uid-border-default, rgba(0, 0, 0, 0.1));
}
.admin-permission-matrix__table td {
  padding: 6px 8px;
  border-bottom: 1px solid var(--uid-border-subtle, rgba(0, 0, 0, 0.06));
  vertical-align: middle;
}
.admin-permission-matrix__group td {
  background: var(--uid-surface-sunken, rgba(0, 0, 0, 0.03));
}
.admin-permission-matrix__fold {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  font-weight: var(--uid-font-weight-medium, 500);
  cursor: pointer;
}
.admin-permission-matrix__item-label {
  padding-left: 28px !important;
}
.admin-permission-matrix__hint {
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-secondary);
}
.admin-permission-matrix__count {
  color: var(--uid-text-secondary);
}
.admin-permission-matrix__key {
  font-family: var(--uid-font-mono, monospace);
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-secondary);
}
.admin-permission-matrix__col-check {
  width: 1%;
  white-space: nowrap;
  text-align: center;
}
.admin-permission-matrix__none,
.admin-permission-matrix__empty {
  color: var(--uid-text-tertiary, var(--uid-text-secondary));
}
.admin-permission-matrix__empty {
  text-align: center;
  padding: var(--uid-space-lg, 16px) !important;
}
</style>
