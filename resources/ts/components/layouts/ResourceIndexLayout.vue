<script setup lang="ts">
/**
 * ResourceIndexLayout — the live index of a resource on a screen, the backend
 * layout `'admin.resource-index'` (Layout::resourceIndex, see
 * core/src/Layout/ResourceIndex.php).
 *
 * It is the list page itself, ResourceIndexPage — the table with its search,
 * filters, sorting, row and bulk actions, inline edits, reordering and trash,
 * or ResourceTreePage for a hierarchical resource — so a screen that explains
 * a table shows the table, not a link to it. The resource's own label heads
 * it as a section title rather than a page title.
 */
import { computed } from 'vue'
import ResourceIndexPage from '../resource/ResourceIndexPage.vue'
import { useManifestStore } from '../../stores/manifest'

interface Props {
  resource: string
  title?: string | null
}

const props = withDefaults(defineProps<Props>(), { title: null })

const manifest = useManifestStore()
const known = computed(() => manifest.manifest === null || manifest.getResource(props.resource) !== null)
</script>

<template>
  <div v-if="known" class="admin-resource-index-layout" data-testid="resource-index-layout">
    <ResourceIndexPage :slug="resource" :title="title" />
  </div>
</template>

<style>
.admin-resource-index-layout {
  min-width: 0;
}
/* The screen's card already pads its content. */
.admin-resource-index-layout > .admin-page,
.admin-resource-index-layout > .admin-resource-tree-page {
  padding: 0;
}
/* A section of the screen, not a page of its own: a section-sized title. */
.admin-resource-index-layout .admin-page__title,
.admin-resource-index-layout .admin-resource-tree-page__title {
  font-size: var(--uid-font-size-md, 15px);
}
</style>
