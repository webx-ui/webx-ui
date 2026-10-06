<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxTooltip, type BadgeSize } from '@webx-ui/core'
import { statusKey, statusType } from './addresses'
import { useAuditMessages } from './i18n'

/**
 * An answer's code as a badge of its colour, and what it means in the tooltip: the name and what
 * to do about it. A code with no line of its own is explained by its class (4xx…); none at all, or
 * 0, is «no answer».
 */
const props = withDefaults(defineProps<{ code: number | null | undefined; size?: BadgeSize }>(), {
  size: 'sm',
})

useAuditMessages()

const t = useTranslate('webx-audit')

const label = computed(() => (props.code ? String(props.code) : t('page.status-none')))
</script>

<template>
  <wx-tooltip :content="t(`page.${statusKey(props.code)}`)" :max-width="360">
    <wx-badge :type="statusType(props.code ?? 0)" :size="props.size">{{ label }}</wx-badge>
  </wx-tooltip>
</template>
