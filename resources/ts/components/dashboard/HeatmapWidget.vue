<script setup lang="ts">
import { UidCard, UidHeatmap } from '@dskripchenko/ui'

interface HeatmapPoint {
  date: string
  value: number
}

interface Props {
  title?: string
  data: HeatmapPoint[]
  levels?: number
  color?: string
  gap?: number
}

withDefaults(defineProps<Props>(), {
  title: '',
  levels: 5,
  color: undefined,
  gap: 2,
})
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <UidHeatmap :data="data" :levels="levels" :color="color" :gap="gap" />
  </UidCard>
</template>
