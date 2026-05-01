<script setup lang="ts">
import { UidAlert } from '@dskripchenko/ui'

interface Props {
  type: string
}
defineProps<Props>()
</script>

<template>
  <UidAlert variant="warning">
    <template #title>Неизвестный widget-тип: {{ type }}</template>
    Зарегистрируйте через <code>registerWidget('{{ type }}', YourComponent)</code>.
  </UidAlert>
</template>
