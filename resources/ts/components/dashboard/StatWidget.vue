<script setup lang="ts">
import { computed } from 'vue'
import { UidStat } from '@dskripchenko/ui'
import type { StatTone } from '@dskripchenko/ui'

type SemanticTone = 'neutral' | 'positive' | 'negative' | 'warning' | 'info'

interface Props {
  title?: string
  value?: number | string
  prefix?: string
  suffix?: string
  trend?: number
  precision?: number
  tone?: SemanticTone
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  value: 0,
  prefix: '',
  suffix: '',
  trend: undefined,
  precision: 0,
  tone: 'neutral',
  loading: false,
})

const TONE_MAP: Record<SemanticTone, StatTone> = {
  neutral: 'primary',
  positive: 'success',
  negative: 'danger',
  warning: 'warning',
  info: 'info',
}

const uidTone = computed<StatTone>(() => TONE_MAP[props.tone])
</script>

<template>
  <UidStat
    :title="title"
    :value="value"
    :prefix="prefix"
    :suffix="suffix"
    :trend="trend"
    :precision="precision"
    :tone="uidTone"
    :loading="loading"
  />
</template>
