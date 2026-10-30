<script setup lang="ts">
import { computed } from 'vue'
import { WxLink } from '@webx-ui/core'
import { contactHref } from './contacts'

/**
 * A phone or an e-mail of an order as a link — `tel:` to call it from a phone, `mailto:` to
 * write. The click stays here: a contact sits inside a row that opens the order on a click, and
 * calling the buyer is not opening their order.
 */
defineOptions({ name: 'WxOrdersContact' })

const props = defineProps<{
  kind: 'phone' | 'email'
  value: string
}>()

const href = computed(() => contactHref(props.kind, props.value))
</script>

<template>
  <wx-link v-if="href" class="wx-orders-contact" :href="href" @click.stop>{{ value }}</wx-link>
  <span v-else>{{ value }}</span>
</template>

<style scoped>
/* Inline, not the link's flex box: a long address in a narrow cell ends in an ellipsis. */
.wx-link.wx-orders-contact {
  display: inline;
  max-width: 100%;
}
</style>
