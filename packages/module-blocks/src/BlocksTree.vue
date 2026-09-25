<script setup lang="ts">
import { useTranslate, WxRowMenu, type RowAction } from '@webx-ui/module-admin'
import { WxIcon, WxSortableList } from '@webx-ui/core'
import type { ScreenNode } from '@webx-ui/schema'
import { nestedFields } from './content'
import type { BlockNode, BlockType } from './types'

/**
 * One level of the tree, and itself again for every container. Rows reorder among their
 * siblings by drag; a container shows its fields' lists underneath, each with its own
 * "add inside". Moving a block between containers is not a drag but "Move to" in its menu:
 * a drag across allow-rules would need a judge at every drop, and a finger in a sheet has none.
 */
const props = withDefaults(
  defineProps<{
    nodes: BlockNode[]
    catalog: BlockType[]
    parentKey?: string | null
    field?: string | null
    /** The field this level is, for its allow-list and limit; none at the top. */
    fieldNode?: ScreenNode | null
    /** How many blocks this level takes; a nested level reads it off its slot. */
    max?: number | null
    selected?: string | null
    disabled?: boolean
    depth?: number
  }>(),
  {
    parentKey: null,
    field: null,
    fieldNode: null,
    max: null,
    selected: null,
    disabled: false,
    depth: 0,
  },
)

const emit = defineEmits<{
  select: [key: string]
  /** `index` is where the block goes; without it, at the end of the list. */
  add: [parentKey: string | null, field: string | null, node: ScreenNode | null, index?: number]
  remove: [key: string]
  duplicate: [key: string]
  move: [key: string]
  visibility: [key: string, hidden: boolean]
  reorder: [parentKey: string | null, field: string | null, list: BlockNode[]]
}>()

const t = useTranslate('webx-blocks')

function typeOf(node: BlockNode): BlockType | null {
  return props.catalog.find((type) => type.slug === node.type) ?? null
}

function titleOf(node: BlockNode): string {
  return typeOf(node)?.title ?? node.type
}

function fieldsOf(node: BlockNode): ScreenNode[] {
  const type = typeOf(node)

  return type?.content ? nestedFields(type.content.schema) : []
}

function childrenIn(node: BlockNode, field: ScreenNode): BlockNode[] {
  const value = node.values[field.id]

  return Array.isArray(value) ? (value as BlockNode[]) : []
}

/**
 * What a block offers, as the panel's own `···` rather than a row of icons.
 *
 * Three bare icons on a row that is itself a click target is three chances to hit the wrong
 * one, and the tree is the narrowest column on the screen: at the width a nested block has,
 * the icons took the name's place and the row read as a dash. The menu also has room for the
 * word, which an icon only says to whoever has already learned it (§20).
 */
function actionsFor(node: BlockNode, index: number): RowAction[] {
  const limit = props.fieldNode
    ? ((props.fieldNode.props?.max as number | undefined) ?? null)
    : props.max

  return [
    /*
     * The button under the list only appends, so a block meant for the middle of a long page
     * was a trip to the bottom and a drag all the way back up. This one puts it where the
     * editor already is. Not "add before": after the row above is the same place.
     */
    {
      key: 'add-after',
      icon: 'plus',
      label: t('field.add-after'),
      disabled: limit !== null && props.nodes.length >= limit,
      run: () => emit('add', props.parentKey, props.field, props.fieldNode, index + 1),
    },
    {
      key: 'visibility',
      icon: node.hidden === true ? 'eye' : 'eye-off',
      label: node.hidden === true ? t('field.show') : t('field.hide'),
      run: () => emit('visibility', node.key, node.hidden !== true),
    },
    {
      key: 'duplicate',
      icon: 'copy',
      label: t('field.duplicate'),
      run: () => emit('duplicate', node.key),
    },
    {
      key: 'move',
      icon: 'arrow-right',
      label: t('field.move'),
      run: () => emit('move', node.key),
    },
    {
      key: 'remove',
      icon: 'trash',
      label: t('field.remove'),
      danger: true,
      run: () => emit('remove', node.key),
    },
  ]
}
</script>

<template>
  <wx-sortable-list
    :model-value="nodes"
    item-key="key"
    :item-label="(node: BlockNode) => titleOf(node)"
    :disabled="disabled"
    plain
    size="sm"
    :empty-text="depth === 0 ? t('field.empty') : ''"
    class="wx-blocks-tree"
    :class="{ 'is-nested': depth > 0 }"
    @update:model-value="emit('reorder', parentKey, field, $event as BlockNode[])"
  >
    <template #default="{ item, index }">
      <div class="wx-blocks-tree__node">
        <div
          class="wx-blocks-tree__row"
          :class="{ 'is-selected': selected === item.key, 'is-hidden': item.hidden === true }"
          role="button"
          tabindex="0"
          @click="emit('select', item.key)"
          @keydown.enter.prevent="emit('select', item.key)"
          @keydown.space.prevent="emit('select', item.key)"
        >
          <span class="wx-blocks-tree__name">{{ titleOf(item) }}</span>
          <span
            v-if="!typeOf(item)"
            class="wx-blocks-tree__flag is-danger"
            :title="t('field.unknown-type', { type: item.type })"
          >
            <wx-icon name="close-circle" />
          </span>
          <span
            v-else-if="typeOf(item)?.draft"
            class="wx-blocks-tree__flag is-warning"
            :title="t('field.draft-type')"
          >
            <wx-icon name="warning" />
          </span>
          <span
            v-else-if="typeOf(item)?.is_enabled === false"
            class="wx-blocks-tree__flag"
            :title="t('field.disabled-type')"
          >
            <!-- Not the eye: that one now means this block is off, which is a different thing
                 from its type being withdrawn from the catalogue. -->
            <wx-icon name="lock" />
          </span>
          <!-- Always, not only on hover: the actions beside it are invisible at rest, and a
               row that is merely dimmer than its neighbours is not a statement. -->
          <span
            v-if="item.hidden === true"
            class="wx-blocks-tree__flag"
            :title="t('field.hidden-note')"
          >
            <wx-icon name="eye-off" />
          </span>
          <span v-if="!disabled" class="wx-blocks-tree__actions">
            <wx-row-menu :actions="actionsFor(item, index)" :label="titleOf(item)" />
          </span>
        </div>

        <div v-for="slot in fieldsOf(item)" :key="slot.id" class="wx-blocks-tree__kids">
          <blocks-tree
            :nodes="childrenIn(item, slot)"
            :catalog="catalog"
            :parent-key="item.key"
            :field="slot.id"
            :field-node="slot"
            :selected="selected"
            :disabled="disabled"
            :depth="depth + 1"
            @select="emit('select', $event)"
            @add="(p, f, n, i) => emit('add', p, f, n, i)"
            @remove="emit('remove', $event)"
            @duplicate="emit('duplicate', $event)"
            @move="emit('move', $event)"
            @visibility="(key, hidden) => emit('visibility', key, hidden)"
            @reorder="(p, f, list) => emit('reorder', p, f, list)"
          />
          <button
            v-if="!disabled"
            type="button"
            class="wx-blocks-tree__add is-inner"
            @click="emit('add', item.key, slot.id, slot)"
          >
            + {{ t('field.add-inside')
            }}<template v-if="fieldsOf(item).length > 1"> · {{ slot.label ?? slot.id }}</template>
          </button>
        </div>
      </div>
    </template>
  </wx-sortable-list>
</template>

<style scoped>
.wx-blocks-tree :deep(.wx-sortable-list__content) {
  min-width: 0;
}

.wx-blocks-tree__node {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-blocks-tree__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-6) var(--wx-space-8);
  border: 1px solid transparent;
  border-radius: var(--wx-radius-control);
  cursor: pointer;
}

.wx-blocks-tree__row:hover {
  background: var(--wx-bg-subtle);
}

.wx-blocks-tree__row.is-selected {
  background: var(--wx-color-primary-soft);
  border-color: var(--wx-color-primary);
}

.wx-blocks-tree__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
}

/*
 * A switched-off block is dimmed, not struck out or greyed to illegibility: it is still the
 * row an editor clicks to edit it, and the eye beside it is what says why it looks different.
 */
.wx-blocks-tree__row.is-hidden .wx-blocks-tree__name {
  opacity: 0.55;
}

.wx-blocks-tree__flag {
  display: flex;
  color: var(--wx-text-muted);
}

.wx-blocks-tree__flag.is-warning {
  color: var(--wx-color-warning);
}

.wx-blocks-tree__flag.is-danger {
  color: var(--wx-color-danger);
}

/*
 * There at rest, not on hover. The icons that used to live here appeared under the pointer,
 * which on a phone is nowhere: a tree that can be built with a finger has to be one a finger
 * can also take a block out of. One quiet `···` is affordable in a way three icons were not.
 */
.wx-blocks-tree__actions {
  display: flex;
  flex: none;
}

/*
 * Small to look at, big to hit.
 *
 * A collapsed row of actions gives its menu 44 px on a coarse pointer, which is the size of a
 * fingertip and the right size for the target. It is not the right size for the picture: in a
 * row that is a name and one `···`, a 44 px button reads as a third of the row. So the button
 * is drawn at 32 and the missing twelve are given back as an invisible skirt around it — the
 * target stays a fingertip, the row stops looking like a button with a name beside it.
 *
 * The extra `.wx-actions__menu` in the selector is not decoration: the rule it overrides sets
 * the same property on the same element at the same specificity, and without it the winner
 * would be whichever stylesheet the bundler happened to put last.
 */
@media (pointer: coarse) {
  .wx-blocks-tree__actions :deep(.wx-actions__menu .wx-action) {
    --wx-action-size: 32px;
    position: relative;
  }

  .wx-blocks-tree__actions :deep(.wx-actions__menu .wx-action)::after {
    content: '';
    position: absolute;
    inset: -6px;
  }
}

.wx-blocks-tree__kids {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  margin-inline-start: var(--wx-space-14);
  padding-inline-start: var(--wx-space-8);
  border-inline-start: 1px dashed var(--wx-border-default);
}

.wx-blocks-tree__add {
  width: 100%;
  padding: var(--wx-space-6);
  border: 1px dashed var(--wx-border-strong);
  border-radius: var(--wx-radius-control);
  background: transparent;
  color: var(--wx-text-muted);
  font: inherit;
  font-size: var(--wx-font-size-xs);
  cursor: pointer;
}

.wx-blocks-tree__add:hover {
  color: var(--wx-color-primary);
  border-color: var(--wx-color-primary);
  background: var(--wx-color-primary-soft);
}
</style>
