<script setup lang="ts">
/**
 * The Accordion layout — collapsible sections over UidAccordion.
 *
 * The backend sends `sections: [{title, defaultOpen, children}]`. Every section
 * starts closed unless it is `defaultOpen`; in the single mode (the default)
 * opening one closes the other, `multi` lets several stay open. In the single
 * mode only the first `defaultOpen` section opens.
 */
import { ref } from 'vue'
import { UidAccordion, UidAccordionItem, UidStack } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

defineOptions({ inheritAttrs: false })

export interface AccordionSection {
  title: string
  defaultOpen?: boolean
  children?: LayoutNode[]
  items?: LayoutNode[]
}

interface Props {
  sections?: AccordionSection[]
  multi?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  sections: () => [],
  multi: false,
})

function valueOf(idx: number): string {
  return `section-${idx}`
}

function childrenOf(section: AccordionSection): LayoutNode[] {
  return section.children ?? section.items ?? []
}

const initiallyOpen = props.sections
  .map((section, idx) => (section.defaultOpen ? valueOf(idx) : null))
  .filter((v): v is string => v !== null)

// UidAccordion switches to the multiple mode by the model's shape: an array
// keeps several values, a string holds one.
const open = ref<string | string[]>(props.multi ? initiallyOpen : (initiallyOpen[0] ?? ''))
</script>

<template>
  <UidAccordion v-model="open" :multiple="multi" class="admin-accordion-layout">
    <UidAccordionItem
      v-for="(section, idx) in sections"
      :key="valueOf(idx)"
      :value="valueOf(idx)"
      :title="section.title"
    >
      <UidStack direction="column" gap="var(--uid-space-md)" align="stretch">
        <LayoutRenderer
          v-for="(child, cidx) in childrenOf(section)"
          :key="cidx"
          :node="child"
        />
      </UidStack>
    </UidAccordionItem>
  </UidAccordion>
</template>
