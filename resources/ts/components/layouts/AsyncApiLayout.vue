<script setup lang="ts">
/**
 * The AsyncAPI layout — `Layout::asyncapi(...)`: an AsyncAPI document, YAML
 * or JSON, as the reference of @dskripchenko/openapi — servers, channels,
 * operations and messages with their payloads. The reference reports a
 * document it cannot read by itself.
 *
 * The document is inline (`content`), a key of the screen's state (`source`)
 * or fetched from `url`. The package and its styles are loaded on first use.
 */
import DocumentFrame from '../documents/DocumentFrame.vue'
import { loadOpenApi } from '../documents/loaders'
import { cssHeight, useDocumentSource, useLazyModule } from '../documents/useDocumentSource'
import { currentLocale, trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  title?: string | null
  content?: string | null
  source?: string | null
  url?: string | null
  height?: string | number | null
}>(), {
  title: null,
  content: null,
  source: null,
  url: null,
  height: null,
})

const { text, loading, error, empty } = useDocumentSource(props)
const { module, failed } = useLazyModule(loadOpenApi)
// The reference speaks the panel's language where it knows it (en, ru).
const locale = currentLocale() || undefined
</script>

<template>
  <DocumentFrame
    kind="asyncapi"
    :title="title"
    :height="cssHeight(height)"
    :loading="loading || !module || text === null"
    :error="failed ? tr('Не удалось загрузить модуль AsyncAPI') : error"
    :empty="empty ? tr('Документ пуст') : null"
    scroll
  >
    <component :is="module!.AsyncApiReference" :spec="text ?? ''" :locale="locale" />
  </DocumentFrame>
</template>
