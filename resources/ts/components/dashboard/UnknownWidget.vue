<script setup lang="ts">
import { UidAlert } from '@dskripchenko/ui'
import { trSafe as tr, tRaw } from '../../stores/i18n'

interface Props {
  type: string
}
defineProps<Props>()
</script>

<template>
  <UidAlert variant="warning" :title="tRaw('Неизвестный тип виджета: :type', { type })">
    {{ tr('Зарегистрируйте через') }} <code>registerWidget('{{ type }}', YourComponent)</code>.
  </UidAlert>
</template>
