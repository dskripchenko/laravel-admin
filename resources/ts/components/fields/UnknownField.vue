<script setup lang="ts">
/**
 * Fallback на missing field-registration. Видимый dev-warn вместо silent fail —
 * host-проект сразу замечает unregistered field-type.
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
