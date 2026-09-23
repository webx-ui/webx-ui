<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
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
import RubricDialog from './RubricDialog.vue'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { RubricRow } from './types'

/**
 * The sections of the blog: the whole menu of the site, in the order it has on it (§6, §10).
 *
 * One list and nothing beside it. The order here *is* the order on the site, so the screen the
 * section needs most is the one where every rubric is visible at once and can be dragged past
 * the others — and a form taking two thirds of the width is two thirds of that view gone. What
 * a rubric is edited in is a dialog over this list ({@link RubricDialog}), which is also what
 * makes reordering safe: nothing here is half-typed, so a row that moves loses nothing.
 *
 * There is no paginator and no search: a rubric is a section, a site has eight of them, and a
 * menu you have to search is a menu that is already wrong.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/blog' })

const context = useAdmin()
const api = createBlogApi(context)
const route = useRoute()
const router = useRouter()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rubrics = ref<RubricRow[]>([])
const prefix = ref('')
const loading = ref(true)

const canManage = computed(() => context.can('blog.taxonomy.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'rubrics')?.title ??
    t('module.rubrics'),
)

const edit = createModal<
  RubricRow,
  { rubric: RubricRow | null; prefix: string; disabled: boolean }
>(RubricDialog)

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new',
          label: t('rubric.new'),
          icon: 'plus',
          primary: true,
          run: () => void openFor(null),
        },
      ]
    : [],
)

async function load(): Promise<void> {
  loading.value = true

  try {
    const payload = await api.rubrics()
    rubrics.value = payload.data
    prefix.value = payload.prefix
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

/**
 * Which rubric is open, kept in the address.
 *
 * So that a link to "the repairs rubric" is a link somebody can send, and so that coming back
 * from the articles of a rubric opens the rubric rather than the top of the list. The dialog
 * is opened from here rather than bound to the query, because a modal is a promise and not a
 * piece of state: two of them for one address is two dialogs stacked on each other.
 */
let opening = false
/* The rubric this address has already opened — a save rewrites the query with the same id, and
   without this the dialog reopens the moment it is saved. */
let handled: string | null = null

watch(
  [() => route.query.rubric, loading],
  ([asked, busy]) => {
    const id = typeof asked === 'string' && asked !== '' ? asked : null

    if (id === null) {
      handled = null

      return
    }

    if (busy || opening || id === handled) return

    const found = rubrics.value.find((rubric) => rubric.id === Number(id))

    if (found) void openFor(found)
  },
  { immediate: true },
)

function remember(id: number | null): void {
  const query = { ...route.query }

  if (id === null) delete query.rubric
  else query.rubric = String(id)

  void router.replace({ query })
}

/**
 * The dialog, and the address that says it is open.
 *
 * A save reloads rather than patching the row in place: the address of a rubric is worked out
 * by the registry, not by the form, and a renamed section changes the line under its own name
 * and nothing else on this screen.
 */
async function openFor(rubric: RubricRow | null): Promise<void> {
  if (opening) return

  opening = true
  handled = rubric === null ? null : String(rubric.id)
  remember(rubric?.id ?? null)

  try {
    const saved = await edit({ rubric, prefix: prefix.value, disabled: !canManage.value })

    if (saved === undefined) {
      remember(null)

      return
    }

    await load()
    handled = String(saved.id)
    remember(saved.id)
  } finally {
    opening = false
  }
}

/** The articles of this rubric, in the section that lists them. */
function articlesOf(rubric: RubricRow): void {
  void router.push({ path: `${props.base}/articles`, query: { rubric: String(rubric.id) } })
}

/**
 * Into the bin, and only while it is empty.
 *
 * The line stays in the menu and refuses rather than being left out, because "why can I not
 * delete this" is the question, and an item that is not there does not answer it (§6). The
 * number it refuses with is already in the row.
 */
async function remove(rubric: RubricRow): Promise<void> {
  if (rubric.articles_count > 0) {
    toast.warning(t('rubric.delete-blocked'))

    return
  }

  const agreed = await confirm({
    title: t('rubric.delete-title', { name: rubric.name }),
    message: t('rubric.delete-text'),
    confirmText: t('rubric.delete'),
    cancelText: t('rubric.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeRubric(rubric.id)
    toast.success(t('rubric.deleted'))
    remember(null)
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function menuOf(rubric: RubricRow): RowAction[] {
  const items: RowAction[] = [
    { key: 'edit', label: t('rubric.edit'), icon: 'edit', run: () => void openFor(rubric) },
  ]

  if (rubric.articles_count > 0) {
    items.push({
      key: 'articles',
      label: t('rubric.show-articles'),
      icon: 'file-text',
      run: () => articlesOf(rubric),
    })
  }

  if (canManage.value) {
    items.push({
      key: 'delete',
      label: t('rubric.delete'),
      icon: 'trash',
      danger: true,
      run: () => void remove(rubric),
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
    await api.sortRubrics(rubrics.value.map((rubric) => rubric.id))
  } catch (error) {
    toast.danger(message(error, t('rubric.reorder-failed')))
    await load()
  }
}
</script>

<template>
  <wx-list-screen class="wx-rubrics" :title="title" :actions="actions" padding="sm">
    <wx-skeleton v-if="loading" class="wx-rubrics__loading" :rows="5" />

    <wx-empty
      v-else-if="rubrics.length === 0"
      :title="t('rubric.empty')"
      :description="t('rubric.empty-help')"
    />

    <template v-else>
      <!--
        A grip and not the whole row: the row is what opens a rubric, and a list whose rows both
        open and drag is a list where one of the two happens by accident.
      -->
      <wx-sortable-list
        v-model="rubrics"
        class="wx-rubrics__list"
        plain
        item-key="id"
        :item-label="(item: RubricRow) => item.name"
        :disabled="!canManage"
        @move="reorder"
      >
        <template #default="{ item }">
          <button
            type="button"
            class="wx-rubric-row"
            :class="{ 'is-hidden': !item.is_visible }"
            @click="openFor(item)"
          >
            <span class="wx-rubric-row__name">
              <wx-text truncate weight="medium">{{ item.name }}</wx-text>
              <wx-tooltip v-if="!item.is_visible" :content="t('rubric.hidden')">
                <wx-icon name="eye-off" size="sm" />
              </wx-tooltip>
            </span>
            <wx-text size="sm" tone="muted" truncate>
              {{ item.path === null ? t('rubric.no-address') : `/${item.path}` }}
            </wx-text>
          </button>
        </template>

        <!-- How many articles would be left without this section: the number the refusal to
           delete names, said before anybody presses anything (§6). -->
        <template #actions="{ item }">
          <wx-text class="wx-rubric-row__count" size="sm" tone="muted">
            {{ t('rubric.articles', { count: item.articles_count }) }}
          </wx-text>

          <wx-row-menu :actions="menuOf(item)" :label="item.name" />
        </template>
      </wx-sortable-list>

      <!-- What the drag is for, under the rows it is about: over them it read as a heading for
           the list, which it is not — the list is already named by the screen. -->
      <wx-alert class="wx-rubrics__note" type="info" :description="t('rubric.order')" />
    </template>
  </wx-list-screen>
</template>

<style>
.wx-rubrics__loading {
  padding: var(--wx-space-8);
}

/*
 * A plain list draws its rows edge to edge, which is right until one of them is tinted: the
 * highlight then starts exactly at the first letter, so a hovered rubric reads as a stain
 * rather than as a row. The padding is inside the tint, not around it.
 */
.wx-rubrics__list.is-plain .wx-sortable-list__row {
  padding-inline: var(--wx-space-8);
}

.wx-rubrics__note {
  margin-block-start: var(--wx-space-8);
}

.wx-rubrics__list .wx-sortable-list__row:hover {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-rubric-row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  align-items: flex-start;
  /* The row is the target, so it takes the row: a name of four letters should not leave three
     quarters of the line dead. */
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

.wx-rubric-row__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  max-width: 100%;
}

/* A rubric that is not on the site says so quietly, the way the site does: it is still a
   rubric, and its articles still answer. */
.wx-rubric-row.is-hidden {
  color: var(--wx-text-muted);
}

/* The count is beside the menu and must not be pushed into it by a long name. */
.wx-rubric-row__count {
  flex: none;
  font-variant-numeric: tabular-nums;
}
</style>
