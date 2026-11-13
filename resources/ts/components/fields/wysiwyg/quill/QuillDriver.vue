<script setup lang="ts">
/**
 * QuillDriver — Quill 2 as an engine of the Wysiwyg field
 * (`Wysiwyg::make('body')->driver('quill')`).
 *
 * It works on Quill directly, the way Orchid's Quill field does, so the HTML
 * it saves is the HTML Orchid saves: the value goes in as the editor root's
 * innerHTML and comes out as the root's innerHTML. Loading a value is silent —
 * the form changes only when the user edits — and leaves no undo step.
 *
 * `config` (the field's driverConfig):
 *   - toolbar — a Quill toolbar (an array of groups), or a list of Orchid's
 *     group names: text, quote, color, header, list, format, media;
 *   - modules — Quill modules, merged over the toolbar;
 *   - formats — the allowed formats (Quill's whitelist);
 *   - theme — 'snow' (the default) or 'bubble';
 *   - output — 'html' (the default) or 'delta', a JSON string of the Delta.
 *
 * The theme CSS (quill/dist/quill.snow.css) is loaded next to the component by
 * whoever registers the driver — see builtinDrivers.ts.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Quill from 'quill'
import { getAdminClient } from '../../../../stores/registry'
import { adminToast } from '../../../../stores/toast'
import { trSafe as tr } from '../../../../stores/i18n'
import { quillToolbar } from './toolbar'

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

const host = ref<HTMLElement | null>(null)
let quill: Quill | null = null
/** The last value this editor emitted or loaded, to tell an outside change from an echo. */
let current: string | null = null

function isDelta(): boolean {
  return props.config.output === 'delta'
}

function read(): string {
  if (!quill) return ''
  if (isDelta()) return JSON.stringify(quill.getContents())
  // What Orchid stores: the .ql-editor's innerHTML.
  return quill.root.innerHTML
}

function parseDelta(value: string): unknown {
  if (value === '') return []
  try {
    return JSON.parse(value)
  } catch {
    return []
  }
}

/** Whether the editor's text is missing some of the HTML's (whitespace aside). */
function lostText(html: string, text: string): boolean {
  const source = new DOMParser().parseFromString(html, 'text/html').body.textContent ?? ''
  const squash = (t: string): string => t.replace(/\s+/g, '')
  return squash(source) !== squash(text)
}

function load(value: string): void {
  if (!quill) return
  if (isDelta()) {
    quill.setContents(parseDelta(value) as never, 'silent')
  } else {
    // Exactly as Orchid: the HTML goes straight into the root, and Quill
    // builds its blots from it. update('silent') takes the mutations now and
    // without a text-change, so loading does not dirty the form. Quill 2's
    // own HTML comes back byte for byte this way.
    quill.root.innerHTML = value
    quill.update('silent')
    // Markup Quill 2 has no blot for — Quill 1's <ul> and <pre class="ql-syntax">,
    // a <div> — is dropped on that path, text and all (Orchid loses it the
    // same way). When text went missing, the HTML is read again through
    // Quill's clipboard, which converts such markup instead.
    if (lostText(value, quill.getText())) {
      quill.setContents(quill.clipboard.convert({ html: value }), 'silent')
    }
  }
  quill.history.clear()
  current = value
}

function customColor(value: string): string | null {
  return value === 'custom-color'
    ? window.prompt(tr('Код цвета, например #c0ffee или rgba 255, 0, 0, 0.5'))
    : value
}

async function uploadImage(): Promise<void> {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/*'
  input.onchange = async () => {
    const file = input.files?.[0]
    if (!file || !quill) return
    try {
      const fd = new FormData()
      fd.append('file', file)
      const res = await getAdminClient().post<{ url: string }>('/uploads/image', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      const range = quill.getSelection(true)
      quill.insertEmbed(range.index, 'image', res.url, 'user')
    } catch (err) {
      if (typeof console !== 'undefined') console.error('[admin] image upload failed:', err)
      adminToast.error(tr('Не удалось загрузить изображение.'))
    }
  }
  input.click()
}

onMounted(() => {
  if (!host.value) return
  const modules = (props.config.modules as Record<string, unknown> | undefined) ?? {}
  const toolbar = quillToolbar(props.config.toolbar ?? (modules.toolbar as unknown))
  quill = new Quill(host.value, {
    theme: (props.config.theme as string | undefined) ?? 'snow',
    placeholder: props.placeholder ?? undefined,
    readOnly: props.disabled,
    formats: (props.config.formats as string[] | undefined) ?? undefined,
    modules: {
      ...modules,
      toolbar: {
        container: toolbar,
        handlers: {
          image: () => void uploadImage(),
          color: (value: string) => quill?.format('color', customColor(value), 'user'),
          background: (value: string) => quill?.format('background', customColor(value), 'user'),
        },
      },
    },
  })
  load(props.modelValue ?? '')
  quill.on('text-change', () => {
    current = read()
    emit('update:modelValue', current)
  })
})

watch(() => props.modelValue, (next) => {
  const value = next ?? ''
  if (quill && value !== current) load(value)
})

watch(() => props.disabled, (disabled) => quill?.enable(!disabled))

onBeforeUnmount(() => {
  quill?.off('text-change')
  quill = null
})

defineExpose({ editor: () => quill })
</script>

<template>
  <div class="admin-quill">
    <div ref="host" />
  </div>
</template>

<style>
.admin-quill .ql-container {
  min-height: 200px;
  font-size: 14px;
  font-family: inherit;
}
.admin-quill .ql-toolbar.ql-snow,
.admin-quill .ql-container.ql-snow {
  border-color: var(--uid-border-subtle);
}
.admin-quill .ql-toolbar.ql-snow {
  border-radius: var(--uid-radius-md) var(--uid-radius-md) 0 0;
  background: var(--uid-surface-base);
}
.admin-quill .ql-container.ql-snow {
  border-radius: 0 0 var(--uid-radius-md) var(--uid-radius-md);
  background: var(--uid-surface-raised);
  color: var(--uid-text-primary);
}
</style>
