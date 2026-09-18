<script setup lang="ts">
import { computed } from 'vue'
import { WxActions, WxDropdownItem } from '@webx-ui/core'
import type { ActionSize } from '@webx-ui/core'
import type { RowAction } from './types'

/**
 * What one record offers, in every list of the panel: a `···` at the end of the row.
 *
 * Always a menu, even for a single action. The panel used to have three answers to this —
 * a menu on `Pages`, a bare red bin on `SEO`, a toolbar over the selection in the library —
 * and the cost of that is not the extra click, it is that nobody knows where to look. A
 * menu also has room for the word: an unlabelled icon says what it does only to whoever has
 * already learned it (§20).
 *
 * Two rules it keeps for every list rather than leaving to each one. The destructive action
 * goes last, behind a rule, in red — it is never the thing the reader meant to hit. And what
 * somebody may not do is left out, not shown greyed: a menu is a list of what is possible,
 * and a row of dead entries teaches nothing.
 */
const props = withDefaults(
  defineProps<{
    /** What may be done, in the order it should read. */
    actions: RowAction[]
    /** Accessible name of the menu — the record it belongs to. */
    label?: string
    size?: ActionSize
  }>(),
  { label: undefined, size: 'sm' },
)

/** Destructive last, whatever order the caller wrote them in. */
const ordered = computed(() => [
  ...props.actions.filter((action) => !action.danger),
  ...props.actions.filter((action) => action.danger),
])

/** The first destructive item is the one with the rule above it. */
const divided = computed(() => {
  const first = ordered.value.find((action) => action.danger)

  return first && ordered.value[0] !== first ? first.key : null
})
</script>

<template>
  <!--
    `.stop`: the row itself opens the record, and deleting one is not opening it.
    `collapse="always"` is what makes this a menu at every width — the row of icons the
    actions would otherwise draw is never built.
  -->
  <wx-actions
    v-if="ordered.length > 0"
    class="wx-row-menu"
    collapse="always"
    align="end"
    :size="size"
    :aria-label="label"
    @click.stop
  >
    <template #collapsed>
      <template v-for="action in ordered" :key="action.key">
        <hr v-if="action.key === divided" class="wx-dropdown__divider" />

        <wx-dropdown-item
          :icon="action.icon"
          :tone="action.danger ? 'danger' : 'default'"
          :href="action.href"
          :target="action.href ? (action.target ?? '_blank') : undefined"
          :disabled="action.disabled"
          @click="action.run?.()"
        >
          {{ action.label }}
        </wx-dropdown-item>
      </template>
    </template>
  </wx-actions>
</template>

<style scoped>
/*
 * Nothing at rest, grey under the pointer.
 *
 * A grey square at the end of every row is a column of grey squares, and the eye reads them
 * before it reads the rows. Worse, the fill was the same colour the row takes when it is
 * hovered, so the one control the row carries went missing exactly when somebody reached for
 * it. The variable rather than a rule on the button: `WxAction` only ever reads
 * `--wx-action-bg`, never declares it, so an inherited value is what it uses (CLAUDE.md §4).
 */
.wx-row-menu {
  --wx-action-bg: transparent;
}
</style>
