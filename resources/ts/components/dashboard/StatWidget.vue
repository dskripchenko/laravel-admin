<script setup lang="ts">
import { UidStat } from '@dskripchenko/ui'

interface Props {
  title?: string
  value?: number | string
  prefix?: string
  suffix?: string
  trend?: number
  precision?: number
  tone?: 'neutral' | 'positive' | 'negative' | 'warning' | 'info'
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  title: '',
  value: 0,
  prefix: '',
  suffix: '',
  trend: undefined,
  precision: 0,
  tone: 'neutral',
  loading: false,
})
</script>

<template>
  <UidStat
    :title="title"
    :value="value"
    :prefix="prefix"
    :suffix="suffix"
    :trend="trend"
    :precision="precision"
    :tone="tone as never"
    :loading="loading"
  />
</template>
