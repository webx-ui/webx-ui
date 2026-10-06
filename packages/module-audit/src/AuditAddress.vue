<script setup lang="ts">
import { computed } from 'vue'
import { WxLink, WxTooltip, type LinkType } from '@webx-ui/core'
import { splitMiddle } from './addresses'

/**
 * An address on one line, opened in a new tab: a long one loses its middle rather than wrapping,
 * since the start names the site and the end tells two addresses apart. The whole of it is in the
 * panel's tooltip.
 */
const props = defineProps<{ href: string; type?: LinkType; strong?: boolean }>()

const parts = computed(() => splitMiddle(props.href))
</script>

<template>
  <wx-tooltip :max-width="560">
    <wx-link
      :href="props.href"
      target="_blank"
      :type="props.type"
      :weight="props.strong ? 'medium' : undefined"
      class="wx-audit-address"
      ><span class="wx-audit-address__head">{{ parts[0] }}</span
      ><span v-if="parts[1]" class="wx-audit-address__tail">{{ parts[1] }}</span></wx-link
    >
    <template #content>
      <span class="wx-audit-address__full">{{ props.href }}</span>
    </template>
  </wx-tooltip>
</template>

<style scoped>
.wx-audit-address {
  display: flex;
  gap: 0;
  min-width: 0;
  max-width: 100%;
  white-space: nowrap;
}

.wx-audit-address__head {
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

.wx-audit-address__tail {
  flex-shrink: 0;
}

/* The tip is teleported out of this component, and an address has no spaces to wrap at. */
:global(.wx-audit-address__full) {
  overflow-wrap: anywhere;
}
</style>
