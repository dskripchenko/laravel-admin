<script setup lang="ts">
import { UidAlert } from '@dskripchenko/ui'

interface Props {
  type: string
  name?: string
}
defineProps<Props>()
</script>

<template>
  <UidAlert variant="warning">
    <template #title>Неизвестный тип infolist-entry: {{ type }}</template>
    Зарегистрируйте через <code>registerInfolistEntry('{{ type }}', YourComponent)</code>.
  </UidAlert>
</template>
