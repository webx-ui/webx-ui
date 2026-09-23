<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  WxAlert,
  WxEmpty,
  WxIcon,
  WxSkeleton,
  WxSortableList,
  WxText,
  WxTooltip,
} from '@webx-ui/core'
import { useAdmin } from '../admin'
import { useErrorText } from '../errors'
import ListScreen from '../ListScreen.vue'
import RowMenu from '../RowMenu.vue'
import type { RowAction, ScreenAction } from '../types'
import { createCategoriesApi } from './api'
import CategoryCreateDialog from './CategoryCreateDialog.vue'
import type { CategoriesOptions, CategoryRow } from './types'
import { useCategoryWords } from './words'

/**
 * The categories of a module: the whole menu of the site, in the order it has on it.
 *
 * One list and nothing beside it. The order here *is* the order on the site, so the screen the
 * section needs most is the one where every category is visible at once and can be dragged past
 * the others. A category is edited on a page of its own ({@link CategoryEditorPage}) — it has an
 * address, SEO and the fields of the project, and it is a record rather than a line of a menu.
 *
 * There is no paginator and no search: a site has eight of them, and a menu you have to search
 * is a menu that is already wrong.
 */
defineOptions({ name: 'WxCategoriesPage' })

const props = defineProps<{ options: CategoriesOptions }>()

const context = useAdmin()
const api = createCategoriesApi(context, props.options.api)
const router = useRouter()
const w = useCategoryWords(props.options.words)
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<CategoryRow[]>([])
const loading = ref(true)

const canManage = computed(() => context.can(props.options.manage))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === props.options.module)?.title ??
    '',
)

const create = createModal<CategoryRow, { options: CategoriesOptions }>(CategoryCreateDialog)

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: w('new'), icon: 'plus', primary: true, run: () => void add() }]
    : [],
)

function countOf(row: CategoryRow): number {
  return Number((row as unknown as Record<string, unknown>)[props.options.count] ?? 0)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = (await api.list()).data
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

function open(row: CategoryRow): void {
  void router.push(`${props.options.path}/${row.id}`)
}

/** A name in a dialog, and then the page: the rest of a category is written there. */
async function add(): Promise<void> {
  const made = await create({ options: props.options })

  if (made !== undefined) open(made)
}

/**
 * Into the bin, and only while it is empty.
 *
 * The line stays in the menu and refuses rather than being left out, because "why can I not
 * delete this" is the question, and an item that is not there does not answer it. The number it
 * refuses with is already in the row.
 */
async function remove(row: CategoryRow): Promise<void> {
  if (countOf(row) > 0) {
    toast.warning(w('delete-blocked'))

    return
  }

  const agreed = await confirm({
    title: w('delete-title', { name: row.name }),
    message: w('delete-text'),
    confirmText: w('delete'),
    cancelText: w('cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(w('deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function menuOf(row: CategoryRow): RowAction[] {
  const items: RowAction[] = [{ key: 'edit', label: w('edit'), icon: 'edit', run: () => open(row) }]
  const itemsOf = props.options.items

  if (itemsOf && countOf(row) > 0) {
    items.push({
      key: 'items',
      label: w('show-items'),
      icon: 'file-txt',
      run: () => void router.push(itemsOf(row.id)),
    })
  }

  if (row.url && row.is_visible) {
    items.push({ key: 'site', label: w('open-on-site'), icon: 'link', href: row.url })
  }

  if (canManage.value) {
    items.push({
      key: 'delete',
      label: w('delete'),
      icon: 'trash',
      danger: true,
      run: () => void remove(row),
    })
  }

  return items
}

/**
 * The whole order, every time.
 *
 * The list is already in its new order on screen — `WxSortableList` reorders the model before
 * it says anything — so this only has to agree with what is there, and a failure has to put the
 * list back rather than leave the screen disagreeing with the database.
 */
async function reorder(): Promise<void> {
  try {
    await api.reorder(rows.value.map((row) => row.id))
  } catch (error) {
    toast.danger(message(error, w('reorder-failed')))
    await load()
  }
}
</script>

<template>
  <list-screen class="wx-categories" :title="title" :actions="actions" padding="sm">
    <wx-skeleton v-if="loading" class="wx-categories__loading" :rows="5" />

    <wx-empty v-else-if="rows.length === 0" :title="w('empty')" :description="w('empty-help')" />

    <template v-else>
      <!--
        A grip and not the whole row: the row is what opens a category, and a list whose rows
        both open and drag is a list where one of the two happens by accident.
      -->
      <wx-sortable-list
        v-model="rows"
        class="wx-categories__list"
        plain
        item-key="id"
        :item-label="(item: CategoryRow) => item.name"
        :disabled="!canManage"
        @move="reorder"
      >
        <template #default="{ item }">
          <router-link
            class="wx-category-row"
            :class="{ 'is-hidden': !item.is_visible }"
            :to="`${props.options.path}/${item.id}`"
          >
            <span class="wx-category-row__name">
              <wx-text truncate weight="medium">{{ item.name }}</wx-text>
              <wx-tooltip v-if="!item.is_visible" :content="w('hidden')">
                <wx-icon name="eye-off" size="sm" />
              </wx-tooltip>
            </span>
            <wx-text size="sm" tone="muted" truncate>
              {{ item.path === null ? w('no-address') : `/${item.path}` }}
            </wx-text>
          </router-link>
        </template>

        <!-- How many items would be left without this category: the number the refusal to
             delete names, said before anybody presses anything. -->
        <template #actions="{ item }">
          <wx-text class="wx-category-row__count" size="sm" tone="muted">
            {{ w('count', { count: countOf(item) }) }}
          </wx-text>

          <row-menu :actions="menuOf(item)" :label="item.name" />
        </template>
      </wx-sortable-list>

      <!-- What the drag is for, under the rows it is about: over them it read as a heading for
           the list, which it is not — the list is already named by the screen. -->
      <wx-alert class="wx-categories__note" type="info" :description="w('order')" />
    </template>
  </list-screen>
</template>

<style>
.wx-categories__loading {
  padding: var(--wx-space-8);
}

/*
 * A plain list draws its rows edge to edge, which is right until one of them is tinted: the
 * highlight then starts exactly at the first letter, so a hovered row reads as a stain rather
 * than as a row. The padding is inside the tint, not around it.
 */
.wx-categories__list.is-plain .wx-sortable-list__row {
  padding-inline: var(--wx-space-8);
}

.wx-categories__note {
  margin-block-start: var(--wx-space-8);
}

.wx-categories__list .wx-sortable-list__row:hover {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-category-row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  align-items: flex-start;
  /* The row is the target, so it takes the row: a name of four letters should not leave three
     quarters of the line dead. */
  flex: 1 1 auto;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.wx-category-row__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  max-width: 100%;
}

/* A category that is not on the site says so quietly, the way the site does: it is still a
   category, and its items still answer. */
.wx-category-row.is-hidden {
  color: var(--wx-text-muted);
}

/* The count is beside the menu and must not be pushed into it by a long name. */
.wx-category-row__count {
  flex: none;
  font-variant-numeric: tabular-nums;
}
</style>
