<script setup lang="ts">
import { computed } from 'vue'
import { renderMarkdown } from '@webx-ui/module-admin'
import { WxProse } from '@webx-ui/core'

/**
 * A specification, a brief or a report as it was written: markdown, rendered with the panel's own
 * renderer. `WxProse` injects what it is given as it is, and that is safe here because the
 * renderer escapes every character before it builds a tag — what arrives holds only the tags it
 * made, whoever wrote the text, an agent included.
 */
defineOptions({ name: 'WxTasksMarkdown' })

const props = defineProps<{ source: string | null | undefined; empty?: string }>()

const html = computed(() => (props.source ? renderMarkdown(props.source) : ''))
</script>

<template>
  <wx-prose v-if="html" class="wx-tasks-markdown" :html="html" size="sm" />
  <p v-else-if="empty" class="wx-tasks-markdown is-empty">{{ empty }}</p>
</template>

<style scoped>
.wx-tasks-markdown {
  min-width: 0;
  overflow-wrap: anywhere;
}

/*
 * A report is written in sections (`## Done`, `## How to check`), and at the size of a page's
 * headings they outshout the stage they belong to. Here a heading is a label of the text under it.
 */
.wx-tasks-markdown :deep(:is(h1, h2, h3, h4)) {
  margin-block: var(--wx-space-12) var(--wx-space-4);
  font-size: var(--wx-font-size-md);
}

.wx-tasks-markdown :deep(:is(h1, h2, h3, h4):first-child) {
  margin-block-start: 0;
}

.wx-tasks-markdown.is-empty {
  margin: 0;
  color: var(--wx-text-muted);
}
</style>
