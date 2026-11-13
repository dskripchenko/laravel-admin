<script setup lang="ts">
/**
 * AuditValueDiff — the change of one long value: a text line by line (one
 * long line word by word), a JSON value as `path: value` lines; the removed
 * parts struck out on red, the added on green, the unchanged runs folded
 * («… N без изменений», a click unfolds them). See valueDiff.ts.
 */
import { computed, ref } from 'vue'
import { valueDiff, type DiffRow } from './valueDiff'
import { tRaw } from '../../stores/i18n'

interface Props {
  before: unknown
  after: unknown
}
const props = defineProps<Props>()

const unfolded = ref(false)
const diff = computed(() => valueDiff(props.before, props.after, unfolded.value ? Number.MAX_SAFE_INTEGER : 1))
const rows = computed<DiffRow[]>(() => diff.value.rows)
</script>

<template>
  <div class="admin-value-diff" :class="`admin-value-diff--${diff.mode}`" data-testid="value-diff">
    <div class="admin-value-diff__stats">
      <span class="admin-value-diff__added">+{{ diff.added }}</span>
      <span class="admin-value-diff__removed">−{{ diff.removed }}</span>
    </div>
    <div v-if="diff.mode === 'words'" class="admin-value-diff__text">
      <template v-for="(row, i) in rows" :key="i">
        <button
          v-if="row.kind === 'fold'"
          type="button"
          class="admin-value-diff__fold"
          data-testid="value-diff-fold"
          @click="unfolded = true"
        >{{ tRaw('… :n слов без изменений', { n: row.count }) }}</button>
        <del v-else-if="row.kind === 'del'" class="admin-value-diff__del">{{ row.text }}</del>
        <ins v-else-if="row.kind === 'add'" class="admin-value-diff__add">{{ row.text }}</ins>
        <span v-else>{{ row.text }}</span>
      </template>
    </div>
    <ol v-else class="admin-value-diff__lines">
      <li
        v-for="(row, i) in rows"
        :key="i"
        class="admin-value-diff__line"
        :class="`admin-value-diff__line--${row.kind}`"
      >
        <button
          v-if="row.kind === 'fold'"
          type="button"
          class="admin-value-diff__fold"
          data-testid="value-diff-fold"
          @click="unfolded = true"
        >{{ tRaw('… :n строк без изменений', { n: row.count }) }}</button>
        <template v-else>
          <span class="admin-value-diff__sign" aria-hidden="true">{{ row.kind === 'del' ? '−' : row.kind === 'add' ? '+' : ' ' }}</span>
          <span class="admin-value-diff__code">{{ row.text === '' ? ' ' : row.text }}</span>
        </template>
      </li>
    </ol>
  </div>
</template>

<style>
.admin-value-diff {
  margin-top: 2px;
  font-size: 12px;
  border: 1px solid var(--uid-border-subtle, rgba(0, 0, 0, 0.08));
  border-radius: var(--uid-radius-sm, 4px);
  overflow: hidden;
  grid-column: 1 / -1;
}
.admin-value-diff__stats {
  display: flex;
  gap: 8px;
  padding: 2px 8px;
  background: var(--uid-surface-sunken, rgba(0, 0, 0, 0.03));
  font-family: var(--uid-font-mono, monospace);
}
.admin-value-diff__added { color: var(--uid-color-success, #15803d); }
.admin-value-diff__removed { color: var(--uid-color-danger, #b91c1c); }
.admin-value-diff__text {
  padding: 4px 8px;
  line-height: 1.6;
  word-break: break-word;
}
.admin-value-diff__lines {
  list-style: none;
  margin: 0;
  padding: 0;
  font-family: var(--uid-font-mono, monospace);
}
.admin-value-diff__line {
  display: flex;
  gap: 6px;
  padding: 0 8px;
  white-space: pre-wrap;
  word-break: break-word;
}
.admin-value-diff__line--del,
.admin-value-diff__del {
  background: var(--uid-color-danger-subtle, rgba(220, 38, 38, 0.12));
  text-decoration: line-through;
  text-decoration-color: rgba(185, 28, 28, 0.5);
}
.admin-value-diff__line--add,
.admin-value-diff__add {
  background: var(--uid-color-success-subtle, rgba(22, 163, 74, 0.14));
  text-decoration: none;
}
.admin-value-diff__line--same { color: var(--uid-text-secondary); }
.admin-value-diff__sign { flex: none; width: 1ch; color: var(--uid-text-tertiary); }
.admin-value-diff__fold {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  color: var(--uid-text-link, var(--uid-text-secondary));
  cursor: pointer;
}
</style>
