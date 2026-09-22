<script setup lang="ts">
import { WxIcon, WxSortableList, WxText, WxTooltip } from '@webx-ui/core'
import { useTranslate, WxRowMenu, type RowAction } from '@webx-ui/module-admin'
import type { MenuItemRow } from './types'

/**
 * One level of the tree, and every level under it.
 *
 * Nested sortable lists rather than one flat list with indentation: dragging has to change both
 * the order and the parent, and a list that draws nesting without holding it makes the second
 * half a guess about how far sideways somebody dropped a row. Each level is its own list, they
 * share a group, and where a row ends up is where it is.
 *
 * Which level changed is not something the list says — a drag between two of them changes two
 * models and neither is a move within one — so every level reports its own new order and the
 * screen above works out what moved. That keeps this component with nothing to remember.
 */
defineOptions({ name: 'WxMenuBranch' })

const props = withDefaults(
  defineProps<{
    items: MenuItemRow[]
    /** Null for the top level. It travels back out with the new order. */
    parentId?: number | null
    disabled?: boolean
  }>(),
  { parentId: null, disabled: false },
)

const emit = defineEmits<{
  /** This level, in its new order. The screen above decides what that means. */
  reorder: [parentId: number | null, items: MenuItemRow[]]
  edit: [item: MenuItemRow]
  duplicate: [item: MenuItemRow]
  remove: [item: MenuItemRow]
}>()

const t = useTranslate('webx-menu')

function name(item: MenuItemRow): string {
  return item.label === '' ? t('menu.untitled-item') : item.label
}

/**
 * The line under the label: where the item goes, or what it is instead.
 *
 * An item that points at something that is gone says so rather than showing an empty line —
 * that is what a menu built before a module was removed looks like, and it is the one state
 * nobody can work out from the row.
 */
function where(item: MenuItemRow): string {
  if (item.target === 'none') return t('menu.goes-nowhere')
  if (item.target === 'entity' && item.resolved === null) return t('menu.missing-target')

  return item.href ?? t('menu.goes-nowhere')
}

/** An address on somebody else's site: it has a scheme and it is not a path of ours. */
function isExternal(item: MenuItemRow): boolean {
  return item.target === 'url' && /^[a-z][a-z0-9+.-]*:/i.test(item.url ?? '')
}

function actionsFor(item: MenuItemRow): RowAction[] {
  if (props.disabled) return []

  return [
    { key: 'edit', icon: 'edit', label: t('menu.edit'), run: () => emit('edit', item) },
    {
      key: 'duplicate',
      icon: 'copy',
      label: t('menu.duplicate'),
      run: () => emit('duplicate', item),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('menu.delete'),
      danger: true,
      run: () => emit('remove', item),
    },
  ]
}
</script>

<template>
  <wx-sortable-list
    class="wx-menu-branch"
    :class="{ 'is-nested': props.parentId !== null }"
    :model-value="props.items"
    plain
    size="sm"
    group="wx-menu-items"
    item-key="id"
    :item-label="name"
    :disabled="props.disabled"
    :empty-text="''"
    @update:model-value="(next: MenuItemRow[]) => emit('reorder', props.parentId, next)"
  >
    <!--
      Nothing, and on purpose: a leaf's empty list is what makes the leaf something a row can be
      dropped into. The words `Nothing here yet` under every item without children would be the
      loudest thing on the screen.
    -->
    <template #empty><span class="wx-menu-branch__drop" /></template>

    <template #default="{ item }">
      <div class="wx-menu-item" :class="{ 'is-off': !item.visible || !item.available }">
        <button
          type="button"
          class="wx-menu-item__open"
          :disabled="props.disabled"
          @click="emit('edit', item)"
        >
          <span class="wx-menu-item__line">
            <wx-text truncate :weight="item.is_heading ? 'semibold' : 'medium'">
              {{ name(item) }}
            </wx-text>

            <!--
              Two facts and two marks, because they are independent: an internal PDF is opened
              in a new tab, and a partner's link sometimes is not.
            -->
            <wx-tooltip v-if="isExternal(item)" :content="t('menu.external')">
              <wx-icon class="wx-menu-item__mark" name="link" :label="t('menu.external')" />
            </wx-tooltip>

            <wx-tooltip v-if="item.new_tab" :content="t('menu.new-tab')">
              <wx-icon class="wx-menu-item__mark" name="external-link" :label="t('menu.new-tab')" />
            </wx-tooltip>
          </span>

          <wx-text size="sm" tone="muted" truncate>
            <template v-if="item.is_heading">{{ t('menu.heading') }}</template>
            <template v-else>{{ where(item) }}</template>
          </wx-text>
        </button>

        <!--
          The two states that dim a row are said in words as well as in colour: "hidden" is a
          decision somebody made and "not on the site" is one they did not, and grey alone
          cannot tell the two apart.
        -->
        <wx-text v-if="!item.visible" class="wx-menu-item__state" size="sm" tone="muted">
          {{ t('menu.hidden') }}
        </wx-text>
        <wx-text v-else-if="!item.available" class="wx-menu-item__state" size="sm" tone="warning">
          {{ t('menu.unavailable') }}
        </wx-text>
      </div>

      <!--
        The level under this item, always drawn: an empty one is the target that makes nesting
        something a drag can do, rather than something only a form could say.
      -->
      <wx-menu-branch
        :items="item.children"
        :parent-id="item.id"
        :disabled="props.disabled"
        @reorder="(parent: number | null, next: MenuItemRow[]) => emit('reorder', parent, next)"
        @edit="emit('edit', $event)"
        @duplicate="emit('duplicate', $event)"
        @remove="emit('remove', $event)"
      />
    </template>

    <!--
      Left out rather than greyed for a reader who may only look: a menu is a list of what is
      possible, and a row of dead entries teaches nothing.
    -->
    <template v-if="!props.disabled" #actions="{ item }">
      <wx-row-menu :actions="actionsFor(item)" :label="name(item)" />
    </template>
  </wx-sortable-list>
</template>

<style scoped>
.wx-menu-branch.is-nested {
  /* The step that says "under": the grip of a child stands clear of its parent's label. */
  padding-inline-start: var(--wx-space-16);
}

/*
 * A row holds its own children, so the grip and the `···` line up with the label rather than
 * with the middle of the whole branch. The list's rows are its elements and not ours, so this
 * reaches them with `:deep()` — a scope attribute would be on neither.
 */
.wx-menu-branch :deep(.wx-sortable-list__row) {
  align-items: flex-start;
}

.wx-menu-branch :deep(.wx-sortable-list__grip) {
  /* The height of one line of the label, so the grip sits on it and not above it. */
  height: 28px;
}

/* An empty level is a target and not a message: as little of it as can still be hit. */
.wx-menu-branch :deep(.wx-sortable-list__empty) {
  padding: 0;
}

.wx-menu-branch__drop {
  display: block;
  height: var(--wx-space-8);
}

.wx-menu-item {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
  min-width: 0;
  padding-block: var(--wx-space-2);
}

.wx-menu-item__open {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-2);
  /* The label is the target: a four-letter item should not leave the rest of the line dead. */
  flex: 1 1 auto;
  min-width: 0;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-menu-item__open:disabled {
  cursor: default;
}

.wx-menu-item__line {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  max-width: 100%;
}

.wx-menu-item__mark {
  flex: 0 0 auto;
  color: var(--wx-text-placeholder);
}

.wx-menu-item__state {
  flex: 0 0 auto;
}

/*
 * Switched off, or pointing at something the site would not show. Dimmed on the label and the
 * address rather than on the row, so the word that says which of the two it is keeps its own
 * colour — that word is the only thing on the row that explains the grey.
 */
.wx-menu-item.is-off .wx-menu-item__open {
  opacity: 0.55;
}
</style>
