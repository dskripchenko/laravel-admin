<script setup lang="ts">
/**
 * The fallback for a field that was never registered. A visible warning
 * rather than a silent failure, so that the host project notices an
 * unregistered field type at once.
 */
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
    <template #title>{{ tRaw('Неизвестный тип поля: :type', { type }) }}</template>
    <template v-if="name">
      <code>{{ name }}</code> — {{ tr('зарегистрируйте компонент поля через') }}
      <code>registerField('{{ type }}', YourComponent)</code>.
    </template>
  </UidAlert>
</template>
