<script setup lang="ts">
/**
 * ImportWizardPage — 4-step wizard для импорта данных в Resource.
 *
 * Эталон: docs/design_handoff_laravel_admin/screens-secondary.jsx (ImportWizard).
 *
 * Шаги:
 *   1. Загрузка файла — UidFileUpload (CSV/TSV/XLSX)
 *   2. Сопоставление колонок — header'ы файла → field-name'ы ресурса
 *   3. Предпросмотр — таблица первых N строк с warning'ами
 *   4. Импорт — progress-bar + KPI (created/updated/errors)
 *
 * Library предоставляет каркас + state. Конкретные API-вызовы (analyze
 * uploaded file → headers/sample, mapping submit → preview, run import →
 * progress events) — host реализует через emit'ы.
 */
import { computed, ref } from 'vue'
import {
  UidAlert,
  UidButton,
  UidCard,
  UidFileUpload,
  UidProgress,
  UidSelect,
  UidStat,
  UidStepper,
  UidTable,
  type UidTableColumn,
} from '@dskripchenko/ui'

interface ColumnHeader {
  key: string
  label: string
  sample?: string
}

interface Mapping {
  /** Header из файла. */
  source: string
  /** Field-name в ресурсе либо null = пропустить. */
  target: string | null
}

interface PreviewRow extends Record<string, unknown> {
  __warning?: string | null
}

interface ImportProgress {
  created: number
  updated: number
  errors: number
  total: number
}

interface Props {
  /** Заголовок страницы. */
  title?: string
  /** Опции для select'а target field'а на step 2. */
  fieldOptions: Array<{ value: string; label: string }>
  /** Headers + sample из uploaded file (host передаёт после step 1). */
  headers?: ColumnHeader[]
  /** Preview rows с warning'ами (host передаёт после step 2 submit). */
  preview?: PreviewRow[]
  /** Preview-table columns (host передаёт). */
  previewColumns?: UidTableColumn[]
  /** Финальная сводка импорта. */
  progress?: ImportProgress | null
  /** Внешне controlled-mode current step (опц.). */
  step?: number
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Импорт',
  headers: () => [],
  preview: () => [],
  previewColumns: () => [],
  progress: null,
  step: undefined,
})

const emit = defineEmits<{
  /** Файл загружен — host анализирует, отдаёт headers через v-bind. */
  'file-uploaded': [file: File]
  /** Mapping submit — host генерирует preview. */
  'mapping-submit': [mapping: Mapping[]]
  /** Run import — host выполняет с реальной записью. */
  'run-import': []
  /** Cancel wizard. */
  cancel: []
}>()

const STEPS = [
  { label: 'Загрузка', description: 'CSV / TSV / XLSX' },
  { label: 'Сопоставление', description: 'Колонки файла → поля' },
  { label: 'Предпросмотр', description: 'Проверка первых строк' },
  { label: 'Импорт', description: 'Запуск + сводка' },
]

const internalStep = ref(0)
const currentStep = computed(() => props.step ?? internalStep.value)

function setStep(idx: number): void {
  if (props.step !== undefined) return // controlled mode — host сам сдвигает
  if (idx < 0 || idx >= STEPS.length) return
  internalStep.value = idx
}

// Step 1: file upload
const uploadedFiles = ref<Array<{ file: File; id: string }>>([])
function onUpload(files: Array<{ file: File; id: string }>): void {
  uploadedFiles.value = files
  if (files.length > 0) {
    emit('file-uploaded', files[0].file)
  }
}

// Step 2: mapping
const mapping = ref<Record<string, string | null>>({})

function setTarget(source: string, value: string | number | null): void {
  mapping.value[source] = value === null || value === '' ? null : String(value)
}

function buildMapping(): Mapping[] {
  return props.headers.map((h) => ({
    source: h.key,
    target: mapping.value[h.key] ?? null,
  }))
}

function onMappingSubmit(): void {
  emit('mapping-submit', buildMapping())
  setStep(2)
}

// Step 3 → 4
function onConfirmRun(): void {
  emit('run-import')
  setStep(3)
}

// Кнопки навигации
const canGoBack = computed(() => currentStep.value > 0)
const canGoNext = computed(() => {
  switch (currentStep.value) {
    case 0: return uploadedFiles.value.length > 0
    case 1: return Object.values(mapping.value).some((v) => v !== null)
    case 2: return true
    default: return false
  }
})

const fileOptions = computed<Array<{ value: string; label: string }>>(() => {
  return [
    { value: '', label: 'Не импортировать' },
    ...props.fieldOptions,
  ]
})

function onCancel(): void {
  emit('cancel')
}
</script>

<template>
  <section class="admin-page admin-import-wizard">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">{{ title }}</h1>
      </div>
      <div class="admin-page__actions">
        <UidButton variant="ghost" @click="onCancel">Отмена</UidButton>
      </div>
    </header>

    <UidStepper :steps="STEPS" :current="currentStep" />

    <!-- Step 1: Upload -->
    <UidCard v-if="currentStep === 0" padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>1. Загрузите файл</h3>
        <p>CSV / TSV / XLSX, до 50 MB</p>
      </header>
      <UidFileUpload
        :model-value="uploadedFiles"
        accept=".csv,.tsv,.xlsx"
        :max-size="50 * 1024 * 1024"
        :max-files="1"
        primary-text="Перетащите файл сюда или"
        secondary-text="нажмите чтобы выбрать"
        @update:model-value="onUpload"
      />
    </UidCard>

    <!-- Step 2: Mapping -->
    <UidCard v-else-if="currentStep === 1" padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>2. Сопоставьте колонки</h3>
        <p>Выберите поле ресурса для каждой колонки файла</p>
      </header>

      <div v-if="headers.length === 0" class="admin-import-wizard__empty">
        Нет данных — host ещё не отдал headers. Загрузите файл сначала.
      </div>

      <div v-else class="admin-import-wizard__mapping">
        <div v-for="h in headers" :key="h.key" class="admin-import-wizard__map-row">
          <div class="admin-import-wizard__map-source">
            <strong>{{ h.label }}</strong>
            <span v-if="h.sample" class="admin-import-wizard__map-sample">
              «{{ h.sample }}»
            </span>
          </div>
          <span class="admin-import-wizard__map-arrow">→</span>
          <UidSelect
            :model-value="mapping[h.key] ?? ''"
            :options="fileOptions"
            placeholder="Не импортировать"
            @update:model-value="(v) => setTarget(h.key, v)"
          />
        </div>
      </div>
    </UidCard>

    <!-- Step 3: Preview -->
    <UidCard v-else-if="currentStep === 2" padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>3. Предпросмотр</h3>
        <p>Первые {{ preview.length }} строк перед импортом</p>
      </header>

      <UidAlert
        v-if="preview.some((r) => r.__warning)"
        variant="warning"
        style="margin-bottom: var(--uid-space-md);"
      >
        Часть строк имеет предупреждения — проверьте перед импортом.
      </UidAlert>

      <UidTable
        v-if="previewColumns.length > 0"
        :columns="previewColumns"
        :data="preview"
      />
      <div v-else class="admin-import-wizard__empty">
        Host не передал previewColumns/preview.
      </div>
    </UidCard>

    <!-- Step 4: Run + Summary -->
    <UidCard v-else padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>4. Импорт</h3>
      </header>

      <div v-if="!progress" class="admin-import-wizard__empty">
        Запускается импорт…
      </div>
      <template v-else>
        <UidProgress
          v-if="progress.total > 0"
          :model-value="progress.created + progress.updated + progress.errors"
          :max="progress.total"
        />
        <div class="admin-import-wizard__kpi">
          <UidStat title="Создано" :value="progress.created" tone="success" />
          <UidStat title="Обновлено" :value="progress.updated" tone="info" />
          <UidStat title="Ошибки" :value="progress.errors" tone="danger" />
        </div>
      </template>
    </UidCard>

    <!-- Navigation buttons -->
    <footer class="admin-import-wizard__nav">
      <UidButton
        v-if="canGoBack"
        variant="ghost"
        @click="setStep(currentStep - 1)"
      >
        Назад
      </UidButton>
      <span style="flex:1" />
      <UidButton
        v-if="currentStep === 0"
        variant="primary"
        :disabled="!canGoNext"
        @click="setStep(1)"
      >
        Далее
      </UidButton>
      <UidButton
        v-else-if="currentStep === 1"
        variant="primary"
        :disabled="!canGoNext"
        @click="onMappingSubmit"
      >
        Подтвердить mapping
      </UidButton>
      <UidButton
        v-else-if="currentStep === 2"
        variant="primary"
        @click="onConfirmRun"
      >
        Запустить импорт
      </UidButton>
    </footer>
  </section>
</template>

<style>
.admin-import-wizard {
  max-width: 1100px;
  margin: 0 auto;
}
.admin-import-wizard__card {
  margin-top: var(--uid-space-md);
}
.admin-import-wizard__card-hd {
  margin-bottom: var(--uid-space-md);
}
.admin-import-wizard__card-hd h3 {
  margin: 0 0 var(--uid-space-2xs);
  font-size: var(--uid-font-size-md);
  font-weight: var(--uid-font-weight-semibold);
}
.admin-import-wizard__card-hd p {
  margin: 0;
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}
.admin-import-wizard__empty {
  padding: var(--uid-space-md);
  text-align: center;
  color: var(--uid-text-tertiary);
  font-size: var(--uid-font-size-sm);
}
.admin-import-wizard__mapping {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-import-wizard__map-row {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: var(--uid-space-md);
  align-items: center;
}
.admin-import-wizard__map-source {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
}
.admin-import-wizard__map-sample {
  color: var(--uid-text-tertiary);
  font-size: var(--uid-font-size-xs);
  font-style: italic;
}
.admin-import-wizard__map-arrow {
  color: var(--uid-text-tertiary);
  font-size: var(--uid-font-size-md);
}
.admin-import-wizard__kpi {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--uid-space-md);
  margin-top: var(--uid-space-md);
}
.admin-import-wizard__nav {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  margin-top: var(--uid-space-md);
}
</style>
