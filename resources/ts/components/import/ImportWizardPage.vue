<script setup lang="ts">
/**
 * ImportWizardPage — the four-step wizard importing data into a resource.
 *
 * It follows docs/design_handoff_laravel_admin/screens-secondary.jsx
 * (ImportWizard).
 *
 * The steps:
 *   1. The upload — a UidFileUpload taking CSV, TSV or XLSX
 *   2. The mapping — the file's headers onto the resource's field names
 *   3. The preview — a table of the first N rows, with the warnings
 *   4. The import — a progress bar and the KPIs (created, updated, errors)
 *
 * The library provides the frame and the state. The actual API calls —
 * analysing the uploaded file into headers and a sample, submitting the
 * mapping for a preview, running the import and reporting progress — are the
 * host's, through the emitted events.
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
import { trSafe as tr, tRaw } from '../../stores/i18n'

interface ColumnHeader {
  key: string
  label: string
  sample?: string
}

interface Mapping {
  /** A header from the file. */
  source: string
  /** The resource's field name, or null to skip the column. */
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
  /** The page's title. */
  title?: string
  /** The options of the target-field select on step 2. */
  fieldOptions: Array<{ value: string; label: string }>
  /** The headers and the sample from the uploaded file; the host passes them after step 1. */
  headers?: ColumnHeader[]
  /** The preview rows with their warnings; the host passes them after step 2 is submitted. */
  preview?: PreviewRow[]
  /** The preview table's columns, passed by the host. */
  previewColumns?: UidTableColumn[]
  /** The import's final summary. */
  progress?: ImportProgress | null
  /** The current step in the externally controlled mode; optional. */
  step?: number
}

const props = withDefaults(defineProps<Props>(), {
  title: tr('Импорт'),
  headers: () => [],
  preview: () => [],
  previewColumns: () => [],
  progress: null,
  step: undefined,
})

const emit = defineEmits<{
  /** The file has been uploaded: the host analyses it and returns the headers through v-bind. */
  'file-uploaded': [file: File]
  /** The mapping has been submitted: the host builds the preview. */
  'mapping-submit': [mapping: Mapping[]]
  /** Run the import: the host performs it, writing for real. */
  'run-import': []
  /** Cancel wizard. */
  cancel: []
}>()

const STEPS = [
  { label: tr('Загрузка'), description: 'CSV / TSV / XLSX' },
  { label: tr('Сопоставление'), description: tr('Колонки файла → поля') },
  { label: tr('Предпросмотр'), description: tr('Проверка первых строк') },
  { label: tr('Импорт'), description: tr('Запуск + сводка') },
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

// The navigation buttons
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
    { value: '', label: tr('Не импортировать') },
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
        <h1 class="admin-page__title">{{ tr(title) }}</h1>
      </div>
      <div class="admin-page__actions">
        <UidButton variant="ghost" @click="onCancel">{{ tr('Отмена') }}</UidButton>
      </div>
    </header>

    <UidStepper :steps="STEPS" :current="currentStep" />

    <!-- Step 1: Upload -->
    <UidCard v-if="currentStep === 0" padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>{{ tr('1. Загрузите файл') }}</h3>
        <p>{{ tr('CSV / TSV / XLSX, до 50 MB') }}</p>
      </header>
      <UidFileUpload
        :model-value="uploadedFiles"
        accept=".csv,.tsv,.xlsx"
        :max-size="50 * 1024 * 1024"
        :max-files="1"
        :primary-text="tr('Перетащите файл сюда или')"
        :secondary-text="tr('нажмите чтобы выбрать')"
        @update:model-value="onUpload"
      />
    </UidCard>

    <!-- Step 2: Mapping -->
    <UidCard v-else-if="currentStep === 1" padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>{{ tr('2. Сопоставьте колонки') }}</h3>
        <p>{{ tr('Выберите поле ресурса для каждой колонки файла') }}</p>
      </header>

      <div v-if="headers.length === 0" class="admin-import-wizard__empty">
        {{ tr('Нет данных — host ещё не отдал headers. Загрузите файл сначала.') }}
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
            :placeholder="tr('Не импортировать')"
            @update:model-value="(v) => setTarget(h.key, v)"
          />
        </div>
      </div>
    </UidCard>

    <!-- Step 3: Preview -->
    <UidCard v-else-if="currentStep === 2" padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>{{ tr('3. Предпросмотр') }}</h3>
        <p>{{ tRaw('Первые :count строк перед импортом', { count: preview.length }) }}</p>
      </header>

      <UidAlert
        v-if="preview.some((r) => r.__warning)"
        variant="warning"
        style="margin-bottom: var(--uid-space-md);"
      >
        {{ tr('Часть строк имеет предупреждения — проверьте перед импортом.') }}
      </UidAlert>

      <UidTable
        v-if="previewColumns.length > 0"
        :columns="previewColumns"
        :data="preview"
      />
      <div v-else class="admin-import-wizard__empty">
        {{ tr('Host не передал previewColumns/preview.') }}
      </div>
    </UidCard>

    <!-- Step 4: Run + Summary -->
    <UidCard v-else padding="md" class="admin-import-wizard__card">
      <header class="admin-import-wizard__card-hd">
        <h3>{{ tr('4. Импорт') }}</h3>
      </header>

      <div v-if="!progress" class="admin-import-wizard__empty">
        {{ tr('Запускается импорт…') }}
      </div>
      <template v-else>
        <UidProgress
          v-if="progress.total > 0"
          :model-value="progress.created + progress.updated + progress.errors"
          :max="progress.total"
        />
        <div class="admin-import-wizard__kpi">
          <UidStat :title="tr('Создано')" :value="progress.created" tone="success" />
          <UidStat :title="tr('Обновлено')" :value="progress.updated" tone="info" />
          <UidStat :title="tr('Ошибки')" :value="progress.errors" tone="danger" />
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
        {{ tr('Назад') }}
      </UidButton>
      <span style="flex:1" />
      <UidButton
        v-if="currentStep === 0"
        variant="primary"
        :disabled="!canGoNext"
        @click="setStep(1)"
      >
        {{ tr('Далее') }}
      </UidButton>
      <UidButton
        v-else-if="currentStep === 1"
        variant="primary"
        :disabled="!canGoNext"
        @click="onMappingSubmit"
      >
        {{ tr('Подтвердить mapping') }}
      </UidButton>
      <UidButton
        v-else-if="currentStep === 2"
        variant="primary"
        @click="onConfirmRun"
      >
        {{ tr('Запустить импорт') }}
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
