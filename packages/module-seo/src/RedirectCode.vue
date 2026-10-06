<script setup lang="ts">
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxTooltip } from '@webx-ui/core'
import { useSeoMessages } from './i18n'

/**
 * A redirect's code as the audit shows one: a badge of its colour, and what it means on hover.
 * 301 is the usual, so it is the calm one; 302 is the one to have meant.
 */
const props = defineProps<{ code: number }>()

useSeoMessages()

const t = useTranslate('webx-seo')
</script>

<template>
  <wx-tooltip :content="t(props.code === 302 ? 'page.code-302' : 'page.code-301')" :max-width="320">
    <wx-badge :type="props.code === 302 ? 'warning' : 'info'" size="sm" tabindex="0">{{
      props.code
    }}</wx-badge>
  </wx-tooltip>
</template>
