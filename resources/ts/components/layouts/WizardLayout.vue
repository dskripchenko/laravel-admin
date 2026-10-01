<script setup lang="ts">
/**
 * The Wizard layout — a multi-step form over UidWizard, UidWizardLayout and
 * UidStepper.
 *
 * The backend sends the steps as `items: [{type: 'step', title, description,
 * rules, items}]` plus:
 *
 *   - `submitMethod` — the screen method the last step's button calls;
 *   - `freeForm` — the steps may be visited in any order by clicking the
 *     stepper; otherwise only the steps already passed are clickable;
 *   - `persistKey` — the current step and the values entered so far are kept
 *     in localStorage under `admin.wizard.{persistKey}` and restored on the
 *     next visit, until the wizard is submitted. Passwords and files are
 *     never stored.
 *
 * Moving forward checks the step first: its fields' own rules plus the step's
 * `rules`, see ./wizard.ts. The messages land in the form's errors, under the
 * fields themselves.
 */
import { computed, onMounted, ref, watch } from 'vue'
import {
  UidButton,
  UidStack,
  UidStepper,
  UidWizard,
  UidWizardLayout,
  UidWizardStep,
} from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'
import { tryUseFormState } from '../render/formState'
import { useScreenContext } from '../render/screenContext'
import { trSafe as tr } from '../../stores/i18n'
import {
  clearProgress,
  collectFields,
  loadProgress,
  persistableFields,
  saveProgress,
  validateStep,
  type WizardField,
} from './wizard'

defineOptions({ inheritAttrs: false })

export interface WizardStepNode extends Record<string, unknown> {
  title?: string
  description?: string | null
  rules?: Record<string, string[] | string>
  items?: LayoutNode[]
}

interface Props {
  items?: WizardStepNode[]
  submitMethod?: string | null
  freeForm?: boolean
  persistKey?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  submitMethod: null,
  freeForm: false,
  persistKey: null,
})

const form = tryUseFormState()
const screen = useScreenContext()

const current = ref<number>(0)
const wizard = ref<InstanceType<typeof UidWizard> | null>(null)

const steps = computed(() =>
  props.items.map((step, idx) => ({
    label: step.title ?? `${idx + 1}`,
    description: step.description ?? undefined,
  })),
)

const fieldsByStep = computed<WizardField[][]>(() =>
  props.items.map((step) => collectFields(step.items ?? [])),
)

const isLast = computed(() => current.value >= props.items.length - 1)
const running = computed(() => screen?.running.value ?? false)

function checkStep(idx: number): boolean {
  if (!form) return true
  const fields = fieldsByStep.value[idx] ?? []
  const errors = validateStep(fields, props.items[idx]?.rules, (name) => form.getField(name))
  // Clear the step's earlier messages first: a fixed field must lose its error.
  for (const f of fields) form.setError(f.name, null)
  for (const name of Object.keys(props.items[idx]?.rules ?? {})) form.setError(name, null)
  for (const [name, messages] of Object.entries(errors)) form.setError(name, messages)
  return Object.keys(errors).length === 0
}

/** One validator per step, stable across renders, for UidWizardStep. */
const validators = computed(() => props.items.map((_, idx) => () => checkStep(idx)))

function next(): void {
  void wizard.value?.next()
}

function back(): void {
  wizard.value?.prev()
}

async function submit(): Promise<void> {
  if (!props.submitMethod || !screen) return
  // Every step, not just the last: a free-form wizard may have skipped some.
  for (let idx = 0; idx < props.items.length; idx++) {
    if (!checkStep(idx)) {
      current.value = idx
      return
    }
  }
  const ok = await screen.runMethod(props.submitMethod)
  if (ok && props.persistKey) clearProgress(props.persistKey)
}

function canVisit(idx: number): boolean {
  return idx !== current.value && (props.freeForm || idx < current.value)
}

/** UidStepper has no click of its own; the step is found from the event. */
function onStepperClick(event: MouseEvent): void {
  const li = (event.target as HTMLElement | null)?.closest('.uid-stepper__step')
  if (!li?.parentElement) return
  const idx = Array.from(li.parentElement.children).indexOf(li)
  if (idx >= 0 && canVisit(idx)) wizard.value?.goTo(idx)
}

/* --- persistence --- */

const persisted = computed<string[]>(() =>
  persistableFields(fieldsByStep.value.flat()).map((f) => f.name),
)

onMounted(() => {
  if (!props.persistKey) return
  const progress = loadProgress(props.persistKey)
  if (!progress) return
  if (form) {
    for (const name of persisted.value) {
      if (name in progress.values) form.setField(name, progress.values[name])
    }
  }
  current.value = Math.min(Math.max(0, progress.step), Math.max(0, props.items.length - 1))
})

watch(
  () => [current.value, ...persisted.value.map((name) => form?.getField(name))],
  () => {
    if (!props.persistKey) return
    const values: Record<string, unknown> = {}
    for (const name of persisted.value) {
      const value = form?.getField(name)
      if (value !== undefined) values[name] = value
    }
    saveProgress(props.persistKey, { step: current.value, values })
  },
  { deep: true },
)
</script>

<template>
  <UidWizard ref="wizard" v-model="current" :steps="steps" class="admin-wizard-layout">
    <UidWizardLayout>
      <template #stepper>
        <!-- The click is delegated: UidStepper renders the steps without one. -->
        <div
          class="admin-wizard-layout__stepper"
          :class="{ 'admin-wizard-layout__stepper--free': freeForm }"
          @click="onStepperClick"
        >
          <UidStepper :steps="steps" :current="current" />
        </div>
      </template>

      <UidWizardStep
        v-for="(step, idx) in items"
        :key="idx"
        :index="idx"
        :validate="validators[idx]"
      >
        <UidStack direction="column" gap="var(--uid-space-md)" align="stretch">
          <LayoutRenderer v-for="(child, cidx) in step.items ?? []" :key="cidx" :node="child" />
        </UidStack>
      </UidWizardStep>

      <template #nav>
        <UidButton
          variant="ghost"
          class="admin-wizard-layout__back"
          :disabled="current === 0 || running"
          @click="back"
        >
          {{ tr('Назад') }}
        </UidButton>
        <UidButton
          v-if="!isLast"
          variant="primary"
          class="admin-wizard-layout__next"
          @click="next"
        >
          {{ tr('Далее') }}
        </UidButton>
        <UidButton
          v-else-if="submitMethod && screen"
          variant="primary"
          class="admin-wizard-layout__submit"
          :loading="running"
          :disabled="running"
          @click="submit"
        >
          {{ tr('Готово') }}
        </UidButton>
      </template>
    </UidWizardLayout>
  </UidWizard>
</template>

<style>
.admin-wizard-layout {
  --uid-layout-wizard-content-padding: var(--uid-space-lg) 0;
}
.admin-wizard-layout .uid-layout-wizard__stepper,
.admin-wizard-layout .uid-layout-wizard__nav {
  padding-left: 0;
  padding-right: 0;
}
.admin-wizard-layout__stepper .uid-stepper__step--completed,
.admin-wizard-layout__stepper--free .uid-stepper__step:not(.uid-stepper__step--current) {
  cursor: pointer;
}
</style>
