<script setup lang="ts">
/**
 * The AuditTrail layout — the audit timeline of the record a screen shows.
 *
 * The backend's `AuditTrail::for(User::class)->fromState('user_id')` sends the
 * subject type and the state key holding the record's id. The id is read from
 * the nearest record (a screen's state) or, inside a form, from the form's
 * state; with no id yet — a create screen — nothing is shown. The events are
 * loaded from GET /audit/timeline by the same AuditTimeline the resource view
 * page uses.
 *
 * `permission`: without it the layout is hidden entirely.
 */
import { computed } from 'vue'
import AuditTimeline from '../resource/AuditTimeline.vue'
import { tryUseRecord } from '../infolist/recordContext'
import { tryUseFormState } from '../render/formState'
import { useAuthStore } from '../../stores/auth'

defineOptions({ inheritAttrs: false })

interface Props {
  subjectType?: string | null
  idStateKey?: string | null
  limit?: number | null
  permission?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  subjectType: null,
  idStateKey: 'id',
  limit: null,
  permission: null,
})

const record = tryUseRecord()
const form = tryUseFormState()

const subjectId = computed<string | number | null>(() => {
  const key = props.idStateKey || 'id'
  const source = record ?? form?.state ?? {}
  const id = source[key]
  return typeof id === 'string' || typeof id === 'number' ? id : null
})

function permitted(permission: string | null): boolean {
  if (!permission) return true
  try {
    return useAuthStore().hasPermission(permission)
  } catch {
    // No Pinia — nobody to ask, so nothing to show.
    return false
  }
}

const allowed = computed<boolean>(() => permitted(props.permission))
</script>

<template>
  <AuditTimeline
    v-if="allowed && subjectId !== null && subjectId !== ''"
    class="admin-audit-trail-layout"
    :subject-type="subjectType"
    :subject-id="subjectId"
    :limit="limit"
  />
</template>
