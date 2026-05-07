<script setup lang="ts">
/**
 * AddWidgetDialog — modal для выбора типа виджета и его базовой настройки.
 *
 * Список типов берётся из dashboard registry (frontend `listWidgets()`).
 * Каждый тип имеет минимальную форму:
 *   - title (строка)
 *   - size (1..12)
 *   - + per-type config (markdown=content, stat=stat-value/label, etc.)
 *
 * Сохранение — emit `add` с готовым WidgetLayoutItem; родитель кладёт
 * его в dashboard store. Backend'ный data-источник host подключает
 * через permanent backend Widget class либо config'-схему.
 */
import { computed, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import {
  UidButton,
  UidIcon,
  UidInput,
} from '@dskripchenko/ui'
import { listWidgets } from './registry'
import type { WidgetLayoutItem } from '../../stores/dashboard'

interface Props {
  open: boolean
}
const props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  add: [item: WidgetLayoutItem]
}>()

const types = computed<string[]>(() => listWidgets())
const selectedType = ref<string>('')
const title = ref<string>('')
// size/gaugeValue хранятся как string чтобы UidInput'у (defineModel<string>)
// не нужны type-cast'ы. Парсим в number при сохранении.
const size = ref<string>('6')

// Type-specific config поля.
const markdownContent = ref<string>('# Новая заметка\n\nТекст…')
const statLabel = ref<string>('LABEL')
const statValue = ref<string>('0')
const gaugeValue = ref<string>('50')

watch(
  () => props.open,
  (open) => {
    if (open) {
      selectedType.value = ''
      title.value = ''
      size.value = '6'
    }
  },
)

const canAdd = computed<boolean>(() => selectedType.value !== '' && title.value.trim() !== '')

function buildConfig(): Record<string, unknown> {
  switch (selectedType.value) {
    case 'markdown':
      return { content: markdownContent.value }
    case 'stat':
    case 'stats':
      return {
        stats: [{ label: statLabel.value, value: statValue.value }],
      }
    case 'gauge':
      return { value: Number(gaugeValue.value) || 0, min: 0, max: 100 }
    default:
      return {}
  }
}

function onAdd(): void {
  if (!canAdd.value) return
  const slug = `custom.${selectedType.value}.${Date.now()}`
  const sizeNum = Number(size.value) || 6
  emit('add', {
    slug,
    type: selectedType.value,
    size: Math.max(1, Math.min(12, sizeNum)),
    config: { title: title.value, ...buildConfig() },
  })
  emit('close')
}

function close(): void {
  emit('close')
}

const labelOf: Record<string, string> = {
  stat: 'Stat — карточка с числом',
  stats: 'Stats overview — несколько метрик',
  chart: 'Chart — линия / столбцы / донат',
  'bar-chart': 'Bar chart — гистограмма',
  'donut-chart': 'Donut — кольцевая диаграмма',
  'recent-table': 'Recent list — последние записи',
  recent_list: 'Recent list — последние записи',
  'recent-list': 'Recent list — последние записи',
  heatmap: 'Heatmap — тепловая карта',
  gauge: 'Gauge — шкала со значением',
  markdown: 'Markdown — заметка / текст',
}

function showsConfig(t: string): 'markdown' | 'stat' | 'gauge' | null {
  if (t === 'markdown') return 'markdown'
  if (t === 'stat' || t === 'stats') return 'stat'
  if (t === 'gauge') return 'gauge'
  return null
}
</script>

<template>
  <Teleport to="body">
    <Transition name="admin-dialog">
      <div
        v-if="open"
        class="admin-dialog-root"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-widget-title"
      >
        <div class="admin-dialog-root__backdrop" @click="close" />
        <div class="admin-dialog">
          <header class="admin-dialog__hd">
            <h2 id="add-widget-title" class="admin-dialog__title">Добавить виджет</h2>
            <button
              type="button"
              class="admin-dialog__close"
              aria-label="Закрыть"
              @click="close"
            >
              <UidIcon :icon="X" :size="16" />
            </button>
          </header>

          <div class="admin-dialog__body">
            <div class="admin-dialog__field">
              <label class="admin-dialog__label">Тип виджета</label>
              <div class="admin-dialog__type-grid">
                <button
                  v-for="t in types"
                  :key="t"
                  type="button"
                  :class="[
                    'admin-dialog__type-card',
                    { 'admin-dialog__type-card--active': selectedType === t },
                  ]"
                  @click="selectedType = t"
                >
                  <span class="admin-dialog__type-name">{{ t }}</span>
                  <span class="admin-dialog__type-help">{{ labelOf[t] ?? '' }}</span>
                </button>
              </div>
            </div>

            <div class="admin-dialog__field">
              <label class="admin-dialog__label">Заголовок</label>
              <UidInput v-model="title" placeholder="Название виджета" />
            </div>

            <div class="admin-dialog__field">
              <label class="admin-dialog__label">Ширина (1..12)</label>
              <UidInput v-model="size" type="number" />
            </div>

            <!-- Type-specific config -->
            <template v-if="selectedType && showsConfig(selectedType) === 'markdown'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Markdown содержимое</label>
                <textarea
                  v-model="markdownContent"
                  class="admin-dialog__textarea"
                  rows="6"
                />
              </div>
            </template>
            <template v-else-if="selectedType && showsConfig(selectedType) === 'stat'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Метка (LABEL)</label>
                <UidInput v-model="statLabel" />
              </div>
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Значение</label>
                <UidInput v-model="statValue" />
              </div>
            </template>
            <template v-else-if="selectedType && showsConfig(selectedType) === 'gauge'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Значение (0..100)</label>
                <UidInput v-model="gaugeValue" type="number" />
              </div>
            </template>
          </div>

          <footer class="admin-dialog__ft">
            <UidButton variant="ghost" @click="close">Отмена</UidButton>
            <UidButton variant="primary" :disabled="!canAdd" @click="onAdd">
              Добавить
            </UidButton>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style>
.admin-dialog-root {
  position: fixed;
  inset: 0;
  z-index: var(--uid-z-modal, 500);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--uid-space-md);
}
.admin-dialog-root__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
}
.admin-dialog {
  position: relative;
  width: min(560px, 100%);
  max-height: calc(100vh - 32px);
  display: flex;
  flex-direction: column;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-lg);
  box-shadow: var(--uid-shadow-lg);
  overflow: hidden;
}
.admin-dialog__hd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--uid-space-md);
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-dialog__title {
  margin: 0;
  font-size: 16px;
  font-weight: var(--uid-font-weight-semibold);
}
.admin-dialog__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  cursor: pointer;
  color: var(--uid-text-secondary);
}
.admin-dialog__close:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}
.admin-dialog__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--uid-space-md);
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}
.admin-dialog__field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.admin-dialog__label {
  font-size: 12px;
  font-weight: var(--uid-font-weight-medium);
  color: var(--uid-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.admin-dialog__type-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--uid-space-xs);
}
.admin-dialog__type-card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
}
.admin-dialog__type-card:hover {
  border-color: var(--uid-accent);
}
.admin-dialog__type-card--active {
  border-color: var(--uid-accent);
  background: color-mix(in srgb, var(--uid-accent) 8%, transparent);
}
.admin-dialog__type-name {
  font-size: 13px;
  font-weight: var(--uid-font-weight-semibold);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  color: var(--uid-text-primary);
}
.admin-dialog__type-help {
  font-size: 11px;
  color: var(--uid-text-tertiary);
}
.admin-dialog__textarea {
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
.admin-dialog__textarea:focus {
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
  border-color: var(--uid-accent);
}
.admin-dialog__ft {
  display: flex;
  justify-content: flex-end;
  gap: var(--uid-space-xs);
  padding: var(--uid-space-md);
  border-top: 1px solid var(--uid-border-subtle);
}
.admin-dialog-enter-active,
.admin-dialog-leave-active { transition: opacity 200ms ease-out; }
.admin-dialog-enter-active .admin-dialog,
.admin-dialog-leave-active .admin-dialog {
  transition: transform 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.admin-dialog-enter-from,
.admin-dialog-leave-to { opacity: 0; }
.admin-dialog-enter-from .admin-dialog,
.admin-dialog-leave-to .admin-dialog { transform: translateY(8px); }
</style>
