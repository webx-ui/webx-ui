<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  WxAction,
  WxBadge,
  WxSelect,
  WxSortableList,
  type SelectModelValue,
  type SelectOption,
} from '@webx-ui/core'
import { useAdmin } from '../admin'
import { useTranslate } from '../i18n'
import { createCategoriesApi } from './api'

/**
 * The categories a record is in, in the order somebody dragged them into (`wx-categories`).
 *
 * The order is the whole control. The first category is the main one — it goes into the
 * breadcrumbs — so a separate switch for "which is the main one" would be a second control
 * saying what this one already says. A list and not a multi-select for the same reason: a bag of
 * checkboxes has no first. A module without a main category says `main: false`, and the order
 * is then only the order.
 *
 * Which categories are on offer is the module's list, asked from `source` — the path its
 * categories answer at (`blog/rubrics`). The server's type of the same name checks the ids
 * against the model registered for that path, so both halves read the one string.
 *
 * The words are props, with the panel's "category" as the default: a module names its own in the
 * screen description (`trans::webx-blog::article.rubric-add`).
 */
defineOptions({ name: 'WxCategoriesField' })

const props = withDefaults(
  defineProps<{
    /** The API the categories answer at, under the panel's: `blog/rubrics`. */
    source: string
    /** Whether the first one is the main one, and wears the badge that says so. */
    main?: boolean
    disabled?: boolean
    mainText?: string
    addText?: string
    removeText?: string
    emptyText?: string
    noneLeftText?: string
  }>(),
  {
    main: true,
    disabled: false,
    mainText: undefined,
    addText: undefined,
    removeText: undefined,
    emptyText: undefined,
    noneLeftText: undefined,
  },
)

const value = defineModel<number[] | null>({ default: () => [] })

const admin = useAdmin()
const t = useTranslate('webx-admin')

interface Choice {
  id: number
  name: string
}

const catalogue = ref<Choice[]>([])

watch(
  () => props.source,
  async (source) => {
    try {
      const payload = await createCategoriesApi(admin, source).list()
      catalogue.value = payload.data.map((row) => ({ id: row.id, name: row.name }))
    } catch {
      // The chosen ones still draw — by number — and the box offers nothing: a list that did not
      // arrive is not a reason to lose the categories already on the record.
      catalogue.value = []
    }
  },
  { immediate: true },
)

const chosen = computed<number[]>(() => (Array.isArray(value.value) ? value.value : []))

const names = computed(() => new Map(catalogue.value.map((one) => [one.id, one.name])))

/**
 * The chosen categories as rows. Writable, because that is how `WxSortableList` hands a reorder
 * back — the ids are the value, and the rows are only what they are drawn as.
 */
const rows = computed<Choice[]>({
  get: () => chosen.value.map((id) => ({ id, name: names.value.get(id) ?? `#${id}` })),
  set: (next) => {
    value.value = next.map((row) => row.id)
  },
})

const available = computed<SelectOption[]>(() =>
  catalogue.value
    .filter((one) => !chosen.value.includes(one.id))
    .map((one) => ({ label: one.name, value: one.id })),
)

function add(picked: SelectModelValue): void {
  if (typeof picked !== 'number' || chosen.value.includes(picked)) return

  value.value = [...chosen.value, picked]
}

function remove(id: number): void {
  value.value = chosen.value.filter((one) => one !== id)
}
</script>

<template>
  <div class="wx-categories-field">
    <wx-sortable-list
      v-model="rows"
      plain
      size="sm"
      item-key="id"
      item-label="name"
      :disabled="props.disabled"
      :empty-text="props.emptyText ?? t('categories.field-empty')"
    >
      <template #default="{ item, index }">
        <span class="wx-categories-field__name">{{ (item as Choice).name }}</span>
        <!-- Said on the row rather than in a legend under the list: the rule is "the first
             one", and the place to say so is the first one. -->
        <wx-badge v-if="props.main && index === 0" type="primary">
          {{ props.mainText ?? t('categories.field-main') }}
        </wx-badge>
      </template>

      <template #actions="{ item }">
        <wx-action
          type="remove"
          size="sm"
          :disabled="props.disabled"
          :title="props.removeText ?? t('categories.field-remove')"
          @click="remove((item as Choice).id)"
        />
      </template>
    </wx-sortable-list>

    <wx-select
      :model-value="null"
      :options="available"
      :placeholder="props.addText ?? t('categories.field-add')"
      :disabled="props.disabled || available.length === 0"
      :empty-text="props.noneLeftText ?? t('categories.field-none-left')"
      filterable
      teleport
      :aria-label="props.addText ?? t('categories.field-add')"
      @update:model-value="add"
    />
  </div>
</template>

<style scoped>
.wx-categories-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

/*
 * The cell a row's content goes into is a block, so the name and the "main" badge stood against
 * each other with nothing between them. The slot makes a line of its own contents; through
 * `:deep()`, because the cell belongs to `WxSortableList`.
 */
.wx-categories-field :deep(.wx-sortable-list__content) {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* Shrinks rather than grows: a name that pushed the badge to the far end of the row would say
   "main" about the distance instead of about the category. */
.wx-categories-field__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
