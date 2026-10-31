<script setup lang="ts">
/**
 * The kanban widget — the backend's KanbanWidget, or a user-added one.
 *
 * - `mode: 'resource'` — the resource's kanban view (Resource::kanban()),
 *   the index page's ResourceKanbanView with the widget's preset search and
 *   filters instead of the page's: the cards come from POST /{slug}/board.
 *   Read-only unless `editable` and the user may update the resource.
 * - `mode: 'markup'` — a board written as markup, the kit's UidKanban,
 *   read-only.
 *
 * Without a mode, a `resource` means the resource mode. The board scrolls
 * inside the widget.
 */
import { computed } from 'vue'
import { UidAlert } from '@dskripchenko/ui'
import KanbanLayout from '../layouts/KanbanLayout.vue'
import ResourceKanbanView from '../resource/ResourceKanbanView.vue'
import WidgetFrame from './WidgetFrame.vue'
import { useResourceWidget } from './resourceWidget'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  title?: string | null
  mode?: 'resource' | 'markup' | null
  resource?: string | null
  search?: string | null
  filters?: Record<string, unknown> | unknown[] | null
  editable?: boolean | null
  content?: string | null
}>(), {
  title: null,
  mode: null,
  resource: null,
  search: null,
  filters: null,
  editable: false,
  content: null,
})

const byResource = computed(() => props.mode === 'resource' || (props.mode !== 'markup' && !!props.resource))
const { meta, query, canUpdate, canReorder } = useResourceWidget(props, 'kanban')
</script>

<template>
  <WidgetFrame kind="kanban" :title="title">
    <template v-if="byResource">
      <ResourceKanbanView
        v-if="meta && meta.kanban"
        :slug="meta.slug"
        :config="meta.kanban"
        :readonly="!canUpdate"
        :can-reorder="canReorder"
        :query="query"
      />
      <UidAlert v-else variant="info" class="admin-widget-frame__notice">
        {{ tr('Доска недоступна: ресурса нет, у него нет вида «канбан» или у вас нет права его смотреть.') }}
      </UidAlert>
    </template>
    <KanbanLayout v-else :content="content ?? ''" height="100%" />
  </WidgetFrame>
</template>
