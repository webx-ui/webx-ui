<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { toast, WxAction, WxCard, WxTable, WxText, type TableColumn } from '@webx-ui/core'
import { useInboxMessages } from './i18n'
import type { InboxField, InboxForm } from './types'

/**
 * How the form gets onto a page.
 *
 * Two answers and both are one line, because the module's whole public half is one tag (§10):
 * a template prints it, and a block type prints the same thing so that an editor never opens
 * a template at all.
 *
 * Under them, the names. A hidden field is filled in by the page it stands on — the address
 * somebody wrote from, the product they were looking at, the campaign that brought them — and
 * the only way to fill one in is to know what it is called (§2.6).
 */
const props = defineProps<{ form: InboxForm; slug: string; fields: InboxField[] }>()

useInboxMessages()
const t = useTranslate('webx-inbox')

const blade = computed(() => `<x-webx-form slug="${props.slug}" />`)

const block = computed(
  () => `{{-- resources/views/blocks/contact.blade.php --}}\n<x-webx-form slug="${props.slug}" />`,
)

const columns = computed<TableColumn<InboxField>[]>(() => [
  { key: 'key', label: t('fields.name') },
  { key: 'title', label: t('fields.title') },
  { key: 'type', label: t('fields.type'), hideBelow: 520 },
])

/** Only the ones a page can actually fill in or a visitor can actually answer. */
const listed = computed(() => props.fields.filter((field) => field.is_enabled))

async function copy(text: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    toast.success(t('panel.copied'))
  } catch {
    // A browser that refuses the clipboard — an insecure origin, a denied permission — still
    // shows the line, and selecting it by hand is what anybody does next anyway.
  }
}
</script>

<template>
  <div class="wx-inbox-embed">
    <wx-card :title="t('panel.embed-blade')">
      <wx-text size="sm" tone="muted">{{ t('panel.embed-blade-help') }}</wx-text>

      <div class="wx-inbox-embed__snippet">
        <code>{{ blade }}</code>
        <wx-action icon="copy" :title="t('panel.copy')" @click="copy(blade)" />
      </div>
    </wx-card>

    <wx-card :title="t('panel.embed-block')">
      <wx-text size="sm" tone="muted">{{ t('panel.embed-block-help') }}</wx-text>

      <div class="wx-inbox-embed__snippet">
        <code>{{ block }}</code>
        <wx-action icon="copy" :title="t('panel.copy')" @click="copy(block)" />
      </div>
    </wx-card>

    <wx-card :title="t('panel.embed-names')">
      <wx-text size="sm" tone="muted">{{ t('panel.embed-names-help') }}</wx-text>

      <wx-table
        :data="listed"
        :columns="columns"
        row-key="id"
        flush
        :empty-text="t('fields.no-fields')"
      >
        <template #cell-key="{ row }">
          <wx-text mono size="sm">fields[{{ row.key }}]</wx-text>
        </template>

        <template #cell-title="{ row }">
          {{ Object.values(row.title)[0] ?? row.key }}
        </template>

        <template #cell-type="{ row }">
          {{ t(`fields.type-${row.type}`) }}
        </template>
      </wx-table>
    </wx-card>
  </div>
</template>

<style scoped>
.wx-inbox-embed {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-inbox-embed__snippet {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  margin-top: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

.wx-inbox-embed__snippet code {
  flex: 1 1 auto;
  min-width: 0;
  overflow-x: auto;
  white-space: pre;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
}
</style>
