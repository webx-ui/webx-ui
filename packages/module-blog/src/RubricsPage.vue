<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate, WxListScreen } from '@webx-ui/module-admin'
import {
  toast,
  WxButton,
  WxCard,
  WxEmpty,
  WxIcon,
  WxListDetail,
  WxSkeleton,
  WxSortableList,
  WxText,
  WxTooltip,
} from '@webx-ui/core'
import RubricForm from './RubricForm.vue'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { RubricRow } from './types'

/**
 * The sections of the blog: the menu on the left, the one that is open on the right (§10).
 *
 * `WxListDetail` rather than a list and a form on two screens, and the reason is the drag. The
 * order of this list *is* the order of the menu on the site (§6), so it is edited by moving
 * rows — and a screen where moving a row means leaving the record you were editing is a screen
 * where nobody reorders anything.
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
const open = ref(false)
/** A rubric that has not been saved yet — the form of a row that is not in the list. */
const draft = ref(false)

const canManage = computed(() => context.can('blog.taxonomy.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'rubrics')?.title ??
    t('module.rubrics'),
)

/**
 * Which rubric is open, kept in the address.
 *
 * So that a link to "the repairs rubric" is a link somebody can send, and so that coming back
 * from the articles of a rubric lands on the rubric rather than on the top of the list.
 */
const current = computed<number | null>(() => {
  const asked = route.query.rubric

  return typeof asked === 'string' && asked !== '' ? Number(asked) : null
})

const chosen = computed(() => rubrics.value.find((rubric) => rubric.id === current.value) ?? null)

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

/* A rubric chosen while the list was still loading is still chosen once it arrives. */
watch(chosen, (rubric) => {
  if (rubric !== null) open.value = true
})

function choose(rubric: RubricRow): void {
  draft.value = false
  void router.replace({ query: { ...route.query, rubric: String(rubric.id) } })
  open.value = true
}

/**
 * A new one: the form, empty, in the pane — not a dialog.
 *
 * A dialog asking for a name would be a second place to type the same six fields, and the
 * second place is the one that ends up missing whatever is added to the first.
 */
function add(): void {
  draft.value = true
  forget()
  open.value = true
}

function forget(): void {
  const rest = { ...route.query }
  delete rest.rubric
  void router.replace({ query: rest })
}

async function saved(rubric: RubricRow): Promise<void> {
  draft.value = false
  await load()
  void router.replace({ query: { ...route.query, rubric: String(rubric.id) } })
}

async function deleted(): Promise<void> {
  draft.value = false
  forget()
  open.value = false
  await load()
}

function close(): void {
  draft.value = false
  forget()
  open.value = false
}

/** The articles of this rubric, in the section that lists them. */
function articlesOf(rubric: RubricRow): void {
  void router.push({ path: `${props.base}/articles`, query: { rubric: String(rubric.id) } })
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
  <wx-list-screen :title="title" :card="false" fill>
    <template #actions>
      <wx-button v-if="canManage" type="primary" @click="add">
        <template #icon><wx-icon name="plus" /></template>
        {{ t('rubric.new') }}
      </wx-button>
    </template>

    <wx-card class="wx-rubrics" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-rubrics__panes"
        :list-width="320"
        :detail-min="440"
        :detail-label="chosen ? chosen.name : t('rubric.new')"
      >
        <template #list>
          <wx-skeleton v-if="loading" class="wx-rubrics__loading" :rows="5" />

          <wx-empty
            v-else-if="rubrics.length === 0"
            :title="t('rubric.empty')"
            :description="t('rubric.empty-help')"
          />

          <!--
            A grip and not the whole row: the row is what opens a rubric, and a list whose rows
            both open and drag is a list where one of the two happens by accident.
          -->
          <wx-sortable-list
            v-else
            v-model="rubrics"
            class="wx-rubrics__list"
            plain
            size="sm"
            item-key="id"
            :item-label="(item: RubricRow) => item.name"
            :disabled="!canManage"
            :title="t('rubric.order')"
            @move="reorder"
          >
            <template #default="{ item }">
              <button
                type="button"
                class="wx-rubric-row"
                :class="{ 'is-current': item.id === current, 'is-hidden': !item.is_visible }"
                @click="choose(item)"
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

            <!-- How many articles would be left without this section: the number the refusal
                 to delete names, said before anybody presses anything (§6). -->
            <template #actions="{ item }">
              <wx-text class="wx-rubric-row__count" size="sm" tone="muted">
                {{ item.articles_count }}
              </wx-text>
            </template>
          </wx-sortable-list>
        </template>

        <template #empty>
          <wx-empty :description="t('rubric.choose')" />
        </template>

        <template #detail="{ inline, back }">
          <rubric-form
            v-if="draft || chosen"
            :key="draft ? 'new' : String(chosen?.id)"
            :rubric="draft ? null : chosen"
            :prefix="prefix"
            :inline="inline"
            :disabled="!canManage"
            @back="
              () => {
                back()
                close()
              }
            "
            @articles="articlesOf"
            @saved="saved"
            @deleted="deleted"
          />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
/*
 * The card is the screen: what scrolls is inside it, not the page behind it. And the card's
 * corners are the screen's corners — the two panes inside are square and paint their own
 * background right up to the edge, so without the clip they cover the rounding.
 */
.wx-rubrics {
  height: 100%;
  min-height: 0;
}

.wx-rubrics > .wx-card__body {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: inherit;
}

.wx-rubrics__panes {
  flex: 1 1 auto;
  min-height: 0;
}

.wx-rubrics__loading {
  padding: var(--wx-space-16);
}

.wx-rubrics__list {
  padding: var(--wx-space-8) var(--wx-space-12);
}

/*
 * A plain list draws its rows edge to edge, which is right until one of them is tinted: the
 * highlight then starts exactly at the first letter, so the chosen rubric reads as a stain
 * rather than as a row. The padding is inside the tint, not around it.
 */
.wx-rubrics__list.is-plain .wx-sortable-list__row {
  padding-inline: var(--wx-space-8);
}

.wx-rubrics__list.is-plain .wx-sortable-list__head {
  margin-block-end: var(--wx-space-6);
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

/*
 * The chosen rubric, marked on the whole row rather than on the name inside it: the row is
 * what was clicked, and a colour on the words alone leaves the grip and the count looking like
 * they belong to something else. `:has()` because the row is the list's element and the class
 * is ours (CLAUDE.md §4).
 */
.wx-rubrics__list .wx-sortable-list__row:has(.wx-rubric-row.is-current) {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-rubric-row.is-current {
  color: var(--wx-color-primary);
}

.wx-rubric-row__count {
  flex: none;
  font-variant-numeric: tabular-nums;
}
</style>
