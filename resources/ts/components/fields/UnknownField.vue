<script setup lang="ts">
/**
 * Fallback на missing field-registration. Видимый dev-warn вместо silent fail —
 * host-проект сразу замечает unregistered field-type.
 */
import { UidAlert } from '@dskripchenko/ui'

interface Props {
  type: string
  name?: string
}
defineProps<Props>()
</script>

<template>
  <UidAlert variant="warning">
    <template #title>Неизвестный тип поля: {{ type }}</template>
    <template v-if="name">
      <code>{{ name }}</code> — зарегистрируйте field-компонент через
      <code>registerField('{{ type }}', YourComponent)</code>.
    </template>
  </UidAlert>
</template>
