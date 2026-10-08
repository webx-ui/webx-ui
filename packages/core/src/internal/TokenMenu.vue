<script setup lang="ts">
import { nextTick, watch } from 'vue'
import { PopoverAnchor, PopoverContent, PopoverPortal, PopoverRoot } from 'reka-ui'
import type { TokenAnchor, TokenOption } from '../composables/useTokens'

/**
 * The list of placeholders under a field's caret.
 *
 * A Reka popover rather than a box of our own: inside a dialog the page behind is inert, and a
 * click on a list teleported out of the dialog would otherwise count as a click outside it and
 * close the dialog. Focus never moves into the list — the field keeps the caret and the keys, and
 * says which option is active through `aria-activedescendant`.
 */
defineOptions({ name: 'WxTokenMenu' })

const props = defineProps<{
  open: boolean
  items: TokenOption[]
  active: number
  anchor?: TokenAnchor
  /** The id of the list, for the field's `aria-controls`. */
  listId: string
  /** Shown over the list when it was opened by the button rather than by typing. */
  title?: string
  /** The field: a click inside it is the caret moving, not a reason to close. */
  owner?: HTMLElement | null
}>()

const emit = defineEmits<{
  choose: [token: TokenOption]
  hover: [index: number]
  close: []
}>()

function onOutside(event: Event): void {
  const target = event.target as Node | null
  if (target && props.owner?.contains(target)) event.preventDefault()
}

/* The active option stays in view while the arrows walk past the edge of a long list. */
watch(
  () => [props.active, props.open],
  () =>
    void nextTick(() => {
      const option = document.getElementById(`${props.listId}-${props.active}`)
      option?.scrollIntoView?.({ block: 'nearest' })
    }),
)
</script>

<template>
  <popover-root :open="open" @update:open="(value: boolean) => !value && emit('close')">
    <popover-anchor :reference="anchor" as-child><span hidden /></popover-anchor>
    <popover-portal>
      <popover-content
        class="wx-token-menu"
        side="bottom"
        align="start"
        :side-offset="4"
        :collision-padding="8"
        @open-auto-focus.prevent
        @close-auto-focus.prevent
        @interact-outside="onOutside"
      >
        <div v-if="title" class="wx-token-menu__title">{{ title }}</div>
        <div :id="listId" class="wx-token-menu__list" role="listbox">
          <div
            v-for="(item, index) in items"
            :id="`${listId}-${index}`"
            :key="item.name"
            class="wx-token-menu__option"
            :class="{ 'is-active': index === active }"
            role="option"
            :aria-selected="index === active"
            @mousedown.prevent
            @mouseenter="emit('hover', index)"
            @click="emit('choose', item)"
          >
            <span class="wx-token-menu__name">[{{ item.name }}]</span>
            <span v-if="item.value" class="wx-token-menu__value">{{ item.value }}</span>
            <span v-if="item.description" class="wx-token-menu__description">{{
              item.description
            }}</span>
          </div>
        </div>
      </popover-content>
    </popover-portal>
  </popover-root>
</template>

<style>
/* Teleported, so not scoped. */
.wx-token-menu {
  z-index: var(--wx-z-index-popover);
  box-sizing: border-box;
  min-width: 220px;
  max-width: min(360px, calc(100vw - var(--wx-space-16)));
  padding: var(--wx-space-4);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-token-menu__title {
  padding: var(--wx-space-4) var(--wx-space-8) var(--wx-space-6);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-token-menu__list {
  max-height: 260px;
  overflow-y: auto;
}

.wx-token-menu__option {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: baseline;
  gap: 0 var(--wx-space-10);
  padding: var(--wx-space-6) var(--wx-space-8);
  border-radius: var(--wx-radius-xs);
  cursor: pointer;
  user-select: none;
}

.wx-token-menu__option.is-active {
  background: var(--wx-bg-fill);
}

.wx-token-menu__name {
  color: var(--wx-text-strong);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
}

.wx-token-menu__value {
  overflow: hidden;
  color: var(--wx-text-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-token-menu__description {
  grid-column: 1 / -1;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>
