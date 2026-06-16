<script setup lang="ts">
/**
 * FileField — простой uploader без crop UI. Используется для `file` /
 * `image` fieldType'ов. Single-file (multiple временно out of scope).
 *
 * State shape (form-state value):
 *   null | { disk, path, url, name, size, mime }
 *
 * Загрузка: POST /uploads/upload (или /uploads/image для image: true) с
 * FormData через AdminClient (axios with CSRF + cookies из config).
 */
import { computed, ref } from 'vue'
import { Upload, X } from 'lucide-vue-next'
import { UidButton, UidIcon } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { getAdminClient } from '../../stores/registry'
import { adminToast } from '../../stores/toast'

interface UploadedFile {
  disk: string
  path: string
  url: string
  name: string
  size: number
  mime: string
}

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  accept?: string | null
  maxSize?: number | null
  image?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  accept: null,
  maxSize: null,
  image: false,
})

const form = useFormState()
const value = computed<UploadedFile | null>(() => {
  const v = form.getField(props.name)
  return (v && typeof v === 'object' && 'url' in (v as object)) ? (v as UploadedFile) : null
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const dragOver = ref(false)

function pickFile(): void {
  fileInput.value?.click()
}

async function onFile(e: Event): Promise<void> {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (f) await upload(f)
  ;(e.target as HTMLInputElement).value = ''
}

async function onDrop(e: DragEvent): Promise<void> {
  e.preventDefault()
  dragOver.value = false
  const f = e.dataTransfer?.files?.[0]
  if (f) await upload(f)
}

async function upload(file: File): Promise<void> {
  if (props.maxSize !== null && file.size > props.maxSize * 1024) {
    adminToast.error(`Файл больше ${props.maxSize} KB.`)
    return
  }
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    const endpoint = props.image ? '/uploads/image' : '/uploads/upload'
    const res = await getAdminClient().post<UploadedFile>(endpoint, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.setField(props.name, res)
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] upload failed:', err)
    adminToast.error('Не удалось загрузить файл.')
  } finally {
    uploading.value = false
  }
}

function clear(): void {
  form.setField(props.name, null)
}
</script>

<template>
  <div class="admin-file-field">
    <label v-if="label" class="admin-file-field__label">
      {{ label }}<span v-if="required" class="admin-file-field__required">*</span>
    </label>

    <div v-if="!value">
      <div
        class="admin-file-field__drop"
        :class="{ 'admin-file-field__drop--over': dragOver }"
        @click="pickFile"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop="onDrop"
      >
        <UidIcon :icon="Upload" :size="24" />
        <p class="admin-file-field__hint">
          {{ uploading ? 'Загрузка…' : 'Кликните или перетащите файл сюда' }}
        </p>
      </div>
      <input
        ref="fileInput"
        type="file"
        :accept="accept ?? undefined"
        class="admin-file-field__input"
        @change="onFile"
      />
    </div>

    <div v-else class="admin-file-field__preview">
      <div class="admin-file-field__preview-info">
        <a :href="value.url" target="_blank" rel="noopener">{{ value.name }}</a>
        <span class="admin-file-field__preview-meta">{{ value.mime }}</span>
      </div>
      <UidButton variant="ghost" size="sm" @click="clear">
        <UidIcon :icon="X" /> Удалить
      </UidButton>
    </div>

    <p v-if="help && !errorMsg" class="admin-file-field__help">{{ help }}</p>
    <p v-if="errorMsg" class="admin-file-field__error">{{ errorMsg }}</p>
  </div>
</template>

<style scoped>
.admin-file-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.admin-file-field__label {
  font-size: 13px;
  font-weight: 500;
}
.admin-file-field__required {
  color: var(--uid-color-danger, #dc2626);
  margin-left: 2px;
}
.admin-file-field__drop {
  border: 2px dashed var(--uid-color-border, #d1d5db);
  border-radius: 8px;
  padding: 24px;
  text-align: center;
  cursor: pointer;
  transition: background 120ms ease, border-color 120ms ease;
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-file-field__drop:hover,
.admin-file-field__drop--over {
  border-color: var(--uid-color-primary, #2dd4bf);
  background: var(--uid-color-surface-2, #f3f4f6);
}
.admin-file-field__hint {
  margin: 8px 0 0;
  font-size: 13px;
}
.admin-file-field__input {
  display: none;
}
.admin-file-field__preview {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  border: 1px solid var(--uid-color-border, #e5e7eb);
  border-radius: 8px;
}
.admin-file-field__preview-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}
.admin-file-field__preview-meta {
  color: var(--uid-color-text-secondary, #62686f);
  font-size: 12px;
}
.admin-file-field__help {
  font-size: 12px;
  color: var(--uid-color-text-secondary, #62686f);
  margin: 0;
}
.admin-file-field__error {
  font-size: 12px;
  color: var(--uid-color-danger, #dc2626);
  margin: 0;
}
</style>
