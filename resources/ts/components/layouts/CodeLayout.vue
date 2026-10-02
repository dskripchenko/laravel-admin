<script setup lang="ts">
/**
 * The Code layout — `Layout::code($code, 'php')`: a highlighted, copyable
 * block, with an optional caption (a file name, say) and line numbers.
 */
import { UidCode } from '@dskripchenko/ui'

defineOptions({ inheritAttrs: false })

interface Props {
  code?: string | null
  language?: string | null
  title?: string | null
  lineNumbers?: boolean
  maxHeight?: string | null
  wrap?: boolean
}

withDefaults(defineProps<Props>(), {
  code: '',
  language: null,
  title: null,
  lineNumbers: false,
  maxHeight: null,
  wrap: false,
})
</script>

<template>
  <figure class="admin-code-layout">
    <figcaption v-if="title" class="admin-code-layout__title">{{ title }}</figcaption>
    <UidCode
      class="admin-code-layout__code"
      :code="code ?? ''"
      :language="language ?? undefined"
      :line-numbers="lineNumbers"
      :max-height="maxHeight ?? undefined"
      :wrap="wrap"
      copy
    />
  </figure>
</template>

<style>
.admin-code-layout { margin: 0 0 var(--uid-space-md, 12px); min-width: 0; }
.admin-code-layout__title {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
  margin-bottom: var(--uid-space-2xs, 4px);
}
.admin-code-layout__code { width: 100%; }
</style>
