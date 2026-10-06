<script setup lang="ts">
import { WxIcon, WxTooltip } from '@webx-ui/core'
import { useTranslate } from './i18n'

/**
 * A quiet mark beside an address a list shows in the site's main language, because the record
 * has none in the language the list is read in. The address stays in the row — the page opens
 * there — and the mark says, on hover, why it is not this language's.
 *
 * Nothing at all when `locale` is empty: that is the usual row.
 */
const props = defineProps<{ locale?: string | null }>()

const t = useTranslate('webx-admin')
</script>

<template>
  <wx-tooltip
    v-if="props.locale"
    :content="t('links.address-fallback', { locale: props.locale.toUpperCase() })"
    :max-width="280"
  >
    <span class="wx-address-note" tabindex="0" @click.stop>
      <wx-icon name="info" size="14px" :label="t('links.address-fallback-label')" />
    </span>
  </wx-tooltip>
</template>

<style scoped>
.wx-address-note {
  display: inline-flex;
  align-items: center;
  margin-inline-start: var(--wx-space-4);
  color: var(--wx-text-muted);
  vertical-align: middle;
  cursor: help;
}

.wx-address-note:focus-visible {
  outline: none;
  border-radius: var(--wx-radius-xs);
  box-shadow: var(--wx-ring-focus);
}
</style>
