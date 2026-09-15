<script setup lang="ts">
import { computed, ref } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxEmpty, WxInput, WxText } from '@webx-ui/core'
import BlockThumb from './BlockThumb.vue'
import { countType } from './content'
import { useBlocksMessages } from './i18n'
import { groupLabel } from './schema'
import type { BlockNode, BlockType } from './types'

/**
 * "Add a block": the types as cards with their pictures, because a name says nothing about
 * whether "Section" is a full-width band or a container with columns. Only what may go
 * here is offered; what is exhausted is shown greyed with the reason rather than hidden,
 * or the editor goes looking for a block that vanished.
 */
const props = withDefaults(
  defineProps<{
    catalog: BlockType[]
    /** The container a block is being added to, or null at the page level. */
    parent?: BlockType | null
    /** What the container's field allows, when the field narrows it further. */
    allow?: string[] | null
    /** The whole tree, for the per-page limits. */
    tree?: BlockNode[]
    groups?: string[]
    /** Where a person with the right can go and make a type. */
    blocksPath?: string | null
  }>(),
  { parent: null, allow: null, tree: () => [], groups: () => [], blocksPath: null },
)

const { resolve, dismiss, open } = useModal<BlockType>()
useBlocksMessages()

const t = useTranslate('webx-blocks')

const search = ref('')

interface Option {
  type: BlockType
  reason: string | null
}

/** Allowed here at all: the parent's say-so and the type's own, both. */
function allowedHere(type: BlockType): boolean {
  if (props.parent === null) {
    return type.allowed_in === null || type.allowed_in.includes('root')
  }

  const parentAllows = props.allow
    ? props.allow.includes(type.slug)
    : props.parent.allow?.includes(type.slug) === true
  const typeAllows = type.allowed_in === null || type.allowed_in.includes(props.parent.slug)

  return parentAllows && typeAllows
}

const options = computed<Option[]>(() =>
  props.catalog
    .filter((type) => type.is_enabled && type.published !== null && allowedHere(type))
    .map((type) => {
      const count = countType(props.tree, type.slug)
      const exhausted = type.max_per_entity !== null && count >= type.max_per_entity

      return { type, reason: exhausted ? t('field.limit-reached', { count }) : null }
    }),
)

const shown = computed(() => {
  const needle = search.value.trim().toLowerCase()

  if (needle === '') return options.value

  return options.value.filter(
    ({ type }) =>
      type.title.toLowerCase().includes(needle) ||
      type.slug.includes(needle) ||
      (type.description ?? '').toLowerCase().includes(needle),
  )
})

const sections = computed(() => {
  const order = [...props.groups]

  for (const { type } of shown.value) {
    if (!order.includes(type.group)) order.push(type.group)
  }

  const grouped = order
    .map((id) => ({
      id,
      label: groupLabel(id, t),
      options: shown.value.filter((o) => o.type.group === id),
    }))
    .filter((section) => section.options.length > 0)

  const flat = shown.value.length <= 5 || search.value.trim() !== '' || grouped.length < 2

  return flat ? [{ id: '', label: '', options: shown.value }] : grouped
})

/** Why the list is empty, in words: which case it is decides what the person does next. */
const emptyText = computed(() => {
  if (props.catalog.length === 0) return t('field.pick-none')
  if (options.value.length === 0 && props.parent) {
    const allowed = (props.allow ?? props.parent.allow ?? []).filter((slug) =>
      props.catalog.some((type) => type.slug === slug),
    )

    return allowed.length === 0
      ? t('field.pick-nothing-allowed', { parent: props.parent.title })
      : t('field.pick-limited', { parent: props.parent.title, types: allowed.join(', ') })
  }

  return t('field.pick-empty')
})
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('field.pick')" :width="860">
    <div class="wx-block-picker">
      <wx-input
        v-if="options.length > 5"
        v-model="search"
        :placeholder="t('field.search')"
        clearable
        autofocus
      />

      <wx-empty v-if="shown.length === 0" icon="grid" :title="emptyText" size="sm">
        <router-link v-if="catalog.length === 0 && blocksPath" :to="blocksPath">
          {{ t('field.pick-none-link') }}
        </router-link>
      </wx-empty>

      <section v-for="section in sections" :key="section.id" class="wx-block-picker__group">
        <div v-if="section.label" class="wx-block-picker__group-title">{{ section.label }}</div>
        <div class="wx-block-picker__cards">
          <button
            v-for="option in section.options"
            :key="option.type.id"
            type="button"
            class="wx-block-picker__card"
            :class="{ 'is-disabled': option.reason !== null }"
            :disabled="option.reason !== null"
            :title="option.reason ?? undefined"
            @click="resolve(option.type)"
          >
            <block-thumb :thumbnail="option.type.thumbnail" :height="96" />
            <div class="wx-block-picker__body">
              <div class="wx-block-picker__title">{{ option.type.title }}</div>
              <wx-text v-if="option.reason" size="sm" tone="warning">{{ option.reason }}</wx-text>
              <wx-text v-else-if="option.type.description" size="sm" tone="muted">
                {{ option.type.description }}
              </wx-text>
            </div>
          </button>
        </div>
      </section>
    </div>

    <template #footer>
      <wx-button variant="outline" @click="dismiss()">{{ t('field.close') }}</wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-block-picker {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-block-picker__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-block-picker__group-title {
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--wx-color-text-muted);
}

.wx-block-picker__cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--wx-space-12);
}

.wx-block-picker__card {
  display: flex;
  flex-direction: column;
  padding: 0;
  overflow: hidden;
  text-align: start;
  font: inherit;
  color: inherit;
  background: var(--wx-color-surface);
  border: 1px solid var(--wx-color-border);
  border-radius: var(--wx-radius-md);
  cursor: pointer;
}

.wx-block-picker__card:hover:not(:disabled),
.wx-block-picker__card:focus-visible {
  border-color: var(--wx-color-primary);
  outline: none;
}

.wx-block-picker__card.is-disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wx-block-picker__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-block-picker__title {
  font-weight: var(--wx-font-weight-semibold);
}
</style>
