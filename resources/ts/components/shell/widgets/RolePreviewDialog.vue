<script setup lang="ts">
/**
 * Picks the role to view the panel as (useRolePreview). Opened from the user
 * menu; starting reloads the page.
 */
import { computed, ref, watch } from 'vue'
import { UidButton, UidModal, UidSelect } from '@dskripchenko/ui'
import { loadPreviewRoles, startRolePreview, type PreviewRole } from '../../../composables/useRolePreview'
import { adminToast } from '../../../stores/toast'
import { trSafe as tr } from '../../../stores/i18n'

const open = defineModel<boolean>({ default: false })

const roles = ref<PreviewRole[]>([])
const role = ref<string | null>(null)
const loading = ref<boolean>(false)
const starting = ref<boolean>(false)

const options = computed(() => roles.value.map((r) => ({ value: r.slug, label: r.name })))

watch(open, async (value) => {
  if (!value) return
  loading.value = true
  try {
    const payload = await loadPreviewRoles()
    roles.value = payload.roles
    role.value = payload.current?.slug ?? null
  } catch {
    adminToast.error(tr('Не удалось загрузить роли.'))
    open.value = false
  } finally {
    loading.value = false
  }
}, { immediate: true })

async function start(): Promise<void> {
  if (role.value === null) return
  starting.value = true
  try {
    await startRolePreview(role.value)
  } catch {
    adminToast.error(tr('Не удалось переключиться на роль.'))
    starting.value = false
  }
}
</script>

<template>
  <UidModal v-model="open" size="sm" :title="tr('Смотреть как роль')">
    <p class="admin-role-preview__hint">
      {{ tr('Меню, экраны и права будут как у выбранной роли. Вернуться — кнопкой в полосе сверху.') }}
    </p>
    <UidSelect
      v-model="role"
      :options="options"
      :disabled="loading"
      :placeholder="tr('Роль')"
      data-testid="role-preview-select"
    />
    <template #footer>
      <UidButton variant="ghost" @click="open = false">{{ tr('Отмена') }}</UidButton>
      <UidButton
        variant="primary"
        :disabled="role === null"
        :loading="starting"
        data-testid="role-preview-start"
        @click="start"
      >
        {{ tr('Переключиться') }}
      </UidButton>
    </template>
  </UidModal>
</template>

<style>
.admin-role-preview__hint {
  margin: 0 0 12px;
  color: var(--uid-text-secondary);
}
</style>
