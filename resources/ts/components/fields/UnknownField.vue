<script setup lang="ts">
/**
 * Fallback на случай, если в манифесте пришёл field-type, для которого нет
 * зарегистрированного компонента. Видимый предупреждающий блок вместо silent
 * fail'а — host-проект сразу заметит missing-registration в dev'е.
 */
interface Props {
  type: string
  name?: string
}
defineProps<Props>()
</script>

<template>
  <div class="admin-field admin-field--unknown" role="alert">
    <strong>Unknown field type: {{ type }}</strong>
    <span v-if="name"> ({{ name }})</span>
  </div>
</template>

<style>
.admin-field--unknown {
  padding: 8px 12px;
  border: 1px dashed var(--admin-danger, #ef4444);
  border-radius: 6px;
  background: rgba(239, 68, 68, 0.05);
  color: var(--admin-danger, #ef4444);
  font-size: 13px;
}
</style>
