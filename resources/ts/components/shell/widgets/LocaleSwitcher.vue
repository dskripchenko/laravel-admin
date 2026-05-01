<script setup lang="ts">
import { useLocaleStore } from '../../../stores/locale'

const locale = useLocaleStore()

async function onChange(event: Event): Promise<void> {
  const target = event.target as HTMLSelectElement
  await locale.setLocale(target.value)
}
</script>

<template>
  <label class="admin-locale-switcher">
    <span class="visually-hidden">Локаль</span>
    <select
      :value="locale.current"
      class="admin-locale-switcher__select"
      :aria-label="'Выбор локали'"
      @change="onChange"
    >
      <option v-for="loc in locale.available" :key="loc" :value="loc">
        {{ loc.toUpperCase() }}
      </option>
    </select>
  </label>
</template>

<style>
.admin-locale-switcher__select {
  height: 32px;
  padding: 0 8px;
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 6px;
  background: transparent;
  font-size: 13px;
  color: var(--admin-text, #111827);
  cursor: pointer;
}
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
</style>
