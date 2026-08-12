<script setup lang="ts">
import { UidAlert } from '@dskripchenko/ui'
import { trSafe as tr, tRaw } from '../../stores/i18n'

interface Props {
  type: string
  name?: string
}
defineProps<Props>()
</script>

<template>
  <UidAlert variant="warning">
    <template #title>{{ tRaw('Неизвестный тип элемента: :type', { type }) }}</template>
    {{ tr('Зарегистрируйте через') }} <code>registerInfolistEntry('{{ type }}', YourComponent)</code>.
  </UidAlert>
</template>
