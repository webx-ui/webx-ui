<script setup lang="ts">
import { WxBadge } from '@webx-ui/core'
import type { AppliedFilter } from './types'

/**
 * What the filters are set to, in the head of every list of the panel.
 *
 * The table gives the strip its place and its spacing (`#applied`); this gives every section the
 * same chip. Written once because the alternative is what the panel already learned about
 * headings: five sections each answering "what does an applied filter look like" gives five
 * answers, and a reader who learns one list should already know the next.
 *
 * A chip takes one filter off. Taking all of them off lives with the fields it resets, inside
 * the panel the funnel opens — beside the chips it reads as one more chip, and it is the only
 * control in that row that does not remove exactly one thing.
 */
defineProps<{ filters: AppliedFilter[] }>()
</script>

<template>
  <wx-badge
    v-for="filter in filters"
    :key="filter.key"
    class="wx-filter-chip"
    size="sm"
    round
    closable
    @close="filter.clear()"
  >
    {{ filter.label }}
  </wx-badge>
</template>
