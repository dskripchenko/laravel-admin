<script setup lang="ts">
/**
 * TinymceDriver — a self-hosted TinyMCE as an engine of the Wysiwyg field
 * (`Wysiwyg::make('body')->driver('tinymce', ['script' => '/js/tinymce/tinymce.min.js'])`).
 *
 * TinyMCE is not bundled: the driver loads the host's own copy by its script
 * URL (see loadTinymce.ts) and runs it with `license_key: 'gpl'`, with
 * base_url at the script's folder, so the skins, icons, models, themes and
 * plugins come from there too. The value is TinyMCE's getContent(), the same
 * call Orchid's TinyMCE field saves.
 *
 * `config` (the field's driverConfig):
 *   - script — the URL of tinymce.min.js (config admin.wysiwyg.tinymce.script by default);
 *   - height, plugins, toolbar, menubar, language — TinyMCE's options of the same names;
 *   - init — any other TinyMCE options, merged last.
 *
 * Without a script (or when it fails to load) the field shows the HTML in a
 * plain textarea, so the value is neither lost nor rewritten.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { loadTinymce, tinymceBaseUrl, type TinymceEditorLike } from './loadTinymce'
import { getAdminClient } from '../../../../stores/registry'
import { trSafe as tr } from '../../../../stores/i18n'

interface Props {
  modelValue?: string | null
  config?: Record<string, unknown>
  placeholder?: string | null
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  config: () => ({}),
  placeholder: null,
  disabled: false,
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const DEFAULT_PLUGINS = 'lists link image table code'
const DEFAULT_TOOLBAR = 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link image table | code'

const textarea = ref<HTMLTextAreaElement | null>(null)
const failed = ref(false)
let editor: TinymceEditorLike | null = null
let current: string | null = null
let unmounted = false

function onPlainInput(e: Event): void {
  emit('update:modelValue', (e.target as HTMLTextAreaElement).value)
}

async function uploadImage(blobInfo: { blob(): Blob; filename(): string }): Promise<string> {
  const fd = new FormData()
  fd.append('file', blobInfo.blob(), blobInfo.filename())
  const res = await getAdminClient().post<{ url: string }>('/uploads/image', fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return res.url
}

/** The options handed to tinymce.init(). */
function options(script: string | null): Record<string, unknown> {
  const c = props.config
  return {
    target: textarea.value,
    license_key: 'gpl',
    ...(script ? { base_url: tinymceBaseUrl(script), suffix: '.min' } : {}),
    branding: false,
    promotion: false,
    menubar: c.menubar ?? false,
    height: c.height ?? 300,
    plugins: c.plugins ?? DEFAULT_PLUGINS,
    toolbar: c.toolbar ?? DEFAULT_TOOLBAR,
    ...(c.language ? { language: c.language } : {}),
    // As Orchid: links and images keep the URLs they were given.
    convert_urls: false,
    placeholder: props.placeholder ?? undefined,
    readonly: props.disabled,
    images_upload_handler: uploadImage,
    ...((c.init as Record<string, unknown> | undefined) ?? {}),
    setup: (ed: TinymceEditorLike) => {
      ed.on('init', () => {
        current = ed.getContent()
      })
      ed.on('change input undo redo', () => {
        const html = ed.getContent()
        if (html === current) return
        current = html
        emit('update:modelValue', html)
      })
    },
  }
}

onMounted(async () => {
  const script = (props.config.script as string | undefined) ?? null
  try {
    const tinymce = await loadTinymce(script)
    if (unmounted || !textarea.value) return
    const [ed] = await tinymce.init(options(script))
    if (unmounted) {
      ed?.remove()
      return
    }
    editor = ed ?? null
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] TinyMCE is not available:', err)
    failed.value = true
  }
})

watch(() => props.modelValue, (next) => {
  const value = next ?? ''
  if (editor && value !== current) {
    editor.setContent(value)
    current = editor.getContent()
  }
})

watch(() => props.disabled, (disabled) => editor?.mode.set(disabled ? 'readonly' : 'design'))

onBeforeUnmount(() => {
  unmounted = true
  editor?.remove()
  editor = null
})
</script>

<template>
  <div class="admin-tinymce">
    <p v-if="failed" class="admin-field__error" role="alert">
      {{ tr('TinyMCE не подключён: укажите адрес tinymce.min.js в driverConfig.script или admin.wysiwyg.tinymce.script. Пока HTML правится как текст.') }}
    </p>
    <textarea
      v-if="failed"
      class="admin-tinymce__plain"
      :value="modelValue ?? ''"
      :disabled="disabled"
      rows="12"
      @input="onPlainInput"
    />
    <textarea v-else ref="textarea" :value="modelValue ?? ''" />
  </div>
</template>

<style>
.admin-tinymce__plain {
  width: 100%;
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 13px;
}
</style>
