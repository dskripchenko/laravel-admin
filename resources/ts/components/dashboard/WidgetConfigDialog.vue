<script setup lang="ts">
/**
 * WidgetConfigDialog — единый dialog для двух use-case'ов:
 *
 *   mode='add'      — выбор типа из registry + первичная настройка
 *   mode='configure'— редактирование существующего widget'а (тип фиксирован)
 *
 * Поля:
 *   - title (всегда)
 *   - size 1..12 (всегда)
 *   - type-specific config (зависит от type):
 *       markdown → content
 *       stat/stats → label + value
 *       gauge → value (0..100)
 *       chart/bar-chart/donut-chart → labels + datasets[0].data (CSV)
 *       recent-table → resource slug + limit
 *       heatmap → нет настраиваемых (рендерится из data, host'ит backend)
 *
 * @add  — emit готового WidgetLayoutItem (родитель кладёт в store).
 * @save — emit patch'а для существующего widget'а.
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
  /** 'add' — выбор типа; 'configure' — редактирование существующего. */
  mode?: 'add' | 'configure'
  /** Initial-state для configure (slug + type + size + config). */
  item?: WidgetLayoutItem | null
  /** Title виджета на момент открытия (для configure-mode из manifest'а). */
  initialTitle?: string
}
const props = withDefaults(defineProps<Props>(), {
  mode: 'add',
  item: null,
  initialTitle: '',
})

const emit = defineEmits<{
  close: []
  add: [item: WidgetLayoutItem]
  save: [patch: Partial<WidgetLayoutItem>]
}>()

const types = computed<string[]>(() => listWidgets())
const selectedType = ref<string>('')
const title = ref<string>('')
const size = ref<string>('6')

const markdownContent = ref<string>('# Новая заметка\n\nТекст…')
const statLabel = ref<string>('LABEL')
const statValue = ref<string>('0')
const gaugeValue = ref<string>('50')
const chartLabels = ref<string>('Янв, Фев, Мар, Апр')
const chartValues = ref<string>('10, 20, 15, 30')
const recentResource = ref<string>('')
const recentLimit = ref<string>('5')

watch(
  () => props.open,
  (open) => {
    if (!open) return
    if (props.mode === 'configure' && props.item) {
      const cfg = (props.item.config ?? {}) as Record<string, unknown>
      selectedType.value = props.item.type ?? ''
      title.value = (cfg.title as string | undefined) ?? props.initialTitle
      size.value = String(props.item.size ?? 6)
      // Преварительно заполняем type-specific поля.
      markdownContent.value = (cfg.content as string | undefined) ?? markdownContent.value
      const firstStat = (cfg.stats as Array<Record<string, unknown>> | undefined)?.[0]
      if (firstStat) {
        statLabel.value = String(firstStat.label ?? '')
        statValue.value = String(firstStat.value ?? '')
      }
      gaugeValue.value = String((cfg.value as number | undefined) ?? 50)
      // Chart labels/values — flatten из cfg.labels / cfg.datasets[0].data.
      const labels = cfg.labels as string[] | undefined
      const datasets = cfg.datasets as Array<{ data?: number[] }> | undefined
      if (labels && labels.length > 0) chartLabels.value = labels.join(', ')
      if (datasets && datasets[0]?.data) chartValues.value = datasets[0].data.join(', ')
      recentResource.value = (cfg.resource as string | undefined) ?? ''
      recentLimit.value = String(cfg.limit ?? 5)
    } else {
      // Add-mode: чистый state.
      selectedType.value = ''
      title.value = ''
      size.value = '6'
    }
  },
)

const canSubmit = computed<boolean>(
  () => selectedType.value !== '' && title.value.trim() !== '',
)

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

type ConfigKind =
  | 'markdown'
  | 'stat'
  | 'gauge'
  | 'chart'
  | 'recent'
  | null

function configKind(t: string): ConfigKind {
  if (t === 'markdown') return 'markdown'
  if (t === 'stat' || t === 'stats') return 'stat'
  if (t === 'gauge') return 'gauge'
  if (t === 'chart' || t === 'bar-chart' || t === 'donut-chart') return 'chart'
  if (t === 'recent-table' || t === 'recent_list' || t === 'recent-list') return 'recent'
  return null
}

function buildConfig(): Record<string, unknown> {
  const base: Record<string, unknown> = { title: title.value }
  switch (configKind(selectedType.value)) {
    case 'markdown':
      base.content = markdownContent.value
      break
    case 'stat':
      base.stats = [{ label: statLabel.value, value: statValue.value }]
      break
    case 'gauge':
      base.value = Number(gaugeValue.value) || 0
      base.min = 0
      base.max = 100
      break
    case 'chart': {
      const labels = chartLabels.value.split(',').map((s) => s.trim()).filter(Boolean)
      const data = chartValues.value
        .split(',')
        .map((s) => Number(s.trim()))
        .filter((n) => !Number.isNaN(n))
      base.labels = labels
      base.datasets = [{ label: title.value, data }]
      base.type = selectedType.value === 'donut-chart' ? 'doughnut' : 'bar'
      break
    }
    case 'recent':
      base.resource = recentResource.value
      base.limit = Number(recentLimit.value) || 5
      break
  }
  return base
}

function onSubmit(): void {
  if (!canSubmit.value) return
  const sizeNum = Math.max(1, Math.min(12, Number(size.value) || 6))
  const config = buildConfig()

  if (props.mode === 'configure' && props.item) {
    emit('save', { size: sizeNum, type: selectedType.value, config })
  } else {
    const slug = `custom.${selectedType.value}.${Date.now()}`
    emit('add', {
      slug,
      type: selectedType.value,
      size: sizeNum,
      config,
    })
  }
  emit('close')
}

function close(): void {
  emit('close')
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
        aria-labelledby="widget-config-title"
      >
        <div class="admin-dialog-root__backdrop" @click="close" />
        <div class="admin-dialog">
          <header class="admin-dialog__hd">
            <h2 id="widget-config-title" class="admin-dialog__title">
              {{ mode === 'add' ? 'Добавить виджет' : 'Настроить виджет' }}
            </h2>
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
            <!-- Type selector — только в add-mode. -->
            <div v-if="mode === 'add'" class="admin-dialog__field">
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
            <div v-else class="admin-dialog__field">
              <label class="admin-dialog__label">Тип</label>
              <div class="admin-dialog__readonly">{{ selectedType || 'unknown' }}</div>
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
            <template v-if="configKind(selectedType) === 'markdown'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Markdown содержимое</label>
                <textarea
                  v-model="markdownContent"
                  class="admin-dialog__textarea"
                  rows="6"
                />
              </div>
            </template>
            <template v-else-if="configKind(selectedType) === 'stat'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Метка</label>
                <UidInput v-model="statLabel" />
              </div>
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Значение</label>
                <UidInput v-model="statValue" />
              </div>
            </template>
            <template v-else-if="configKind(selectedType) === 'gauge'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Значение (0..100)</label>
                <UidInput v-model="gaugeValue" type="number" />
              </div>
            </template>
            <template v-else-if="configKind(selectedType) === 'chart'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Метки (через запятую)</label>
                <UidInput v-model="chartLabels" placeholder="Янв, Фев, Мар" />
              </div>
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Значения (через запятую)</label>
                <UidInput v-model="chartValues" placeholder="10, 20, 15" />
              </div>
            </template>
            <template v-else-if="configKind(selectedType) === 'recent'">
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Resource slug</label>
                <UidInput v-model="recentResource" placeholder="articles" />
              </div>
              <div class="admin-dialog__field">
                <label class="admin-dialog__label">Limit</label>
                <UidInput v-model="recentLimit" type="number" />
              </div>
            </template>
          </div>

          <footer class="admin-dialog__ft">
            <UidButton variant="ghost" @click="close">Отмена</UidButton>
            <UidButton variant="primary" :disabled="!canSubmit" @click="onSubmit">
              {{ mode === 'add' ? 'Добавить' : 'Сохранить' }}
            </UidButton>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style>
.admin-dialog__readonly {
  padding: 8px 10px;
  background: var(--uid-surface-base);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 13px;
  color: var(--uid-text-secondary);
}
</style>
