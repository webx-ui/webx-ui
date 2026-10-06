<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxTooltip } from '@webx-ui/core'
import { useAuditMessages } from './i18n'

/**
 * A link's `target` and `rel`, as they are in the markup: `_blank`, `nofollow`, `noopener`… Nothing
 * when the link has neither — a plain link is the usual case, not an empty cell. `_blank` without
 * `noopener` or `noreferrer` is marked: current browsers add `noopener` themselves, older ones
 * hand the opened page control of this one.
 */
const props = defineProps<{ rel?: string | null; target?: string | null }>()

useAuditMessages()

const t = useTranslate('webx-audit')

const rels = computed(() => (props.rel ?? '').split(/\s+/).filter(Boolean))

const opener = computed(
  () =>
    props.target?.toLowerCase() === '_blank' &&
    !rels.value.some((rel) => rel === 'noopener' || rel === 'noreferrer'),
)
</script>

<template>
  <span v-if="props.target || rels.length" class="wx-audit-attrs">
    <wx-tooltip v-if="props.target && opener" :content="t('page.attrs-opener')" :max-width="320">
      <wx-badge type="warning" variant="outline" size="sm">{{ props.target }}</wx-badge>
    </wx-tooltip>
    <wx-badge v-else-if="props.target" variant="outline" size="sm">{{ props.target }}</wx-badge>
    <wx-badge v-for="value in rels" :key="value" variant="outline" size="sm">{{ value }}</wx-badge>
  </span>
</template>

<style scoped>
.wx-audit-attrs {
  display: inline-flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
  min-width: 0;
}
</style>
