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
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  localizedValue,
  toast,
  useLocales,
  WxBadge,
  WxButton,
  WxCard,
  WxEmpty,
  WxListDetail,
  WxSkeleton,
  WxSortableList,
  WxText,
} from '@webx-ui/core'
import FormCreateDialog from './FormCreateDialog.vue'
import SubmissionList from './SubmissionList.vue'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import type { InboxForm } from './types'

/**
 * The section: the forms of the site on the left, what came in through one of them on the
 * right (§11, §2.12).
 *
 * One screen rather than two, and that is the decision this is built around. The columns of
 * the list are the fields of the chosen form, so a list of everything would have no columns
 * to speak of; and the two questions somebody opens this section to ask — "what is new" and
 * "what does this form ask" — are one click apart instead of one screen apart.
 *
 * The right-hand side is the submissions, and it arrives with them. What is here is the
 * column: the forms, in the order somebody dragged them, with what is waiting in each.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/inbox' })

const context = useAdmin()
const api = createInboxApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useInboxMessages()

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const forms = ref<InboxForm[]>([])
const loading = ref(true)
const open = ref(false)

const canManage = computed(() => context.can('inbox.manage'))

const create = createModal<InboxForm, Record<string, never>>(FormCreateDialog)

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'inbox')?.title ??
    t('module.title'),
)

/**
 * Which form is open, kept in the address.
 *
 * So that coming back from a submission lands on the form it belonged to (§11) — and so that
 * a link to "the callback form" is a link somebody can send.
 */
const current = computed<number | null>(() => {
  const asked = route.query.form

  return typeof asked === 'string' && asked !== '' ? Number(asked) : null
})

const chosen = computed(() => forms.value.find((form) => form.id === current.value) ?? null)

function name(form: InboxForm): string {
  return localizedValue(form.title, locales.active.value, form.slug)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    forms.value = await api.forms()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

/* A form chosen while the list was still loading is still chosen once it arrives. */
watch(chosen, (form) => {
  if (form !== null) open.value = true
})

function choose(form: InboxForm): void {
  void router.replace({ query: { ...route.query, form: String(form.id) } })
  open.value = true
}

function edit(form: InboxForm): void {
  void router.push(`${props.base}/forms/${form.id}`)
}

function actionsFor(form: InboxForm): RowAction[] {
  if (!canManage.value) return []

  return [
    { key: 'settings', icon: 'settings', label: t('panel.settings'), run: () => edit(form) },
    { key: 'duplicate', icon: 'copy', label: t('panel.duplicate'), run: () => duplicate(form) },
    // A form that has taken submissions is switched off, never deleted (§2.4). The line is
    // left out rather than greyed: a menu is a list of what is possible.
    ...(form.submissions_count === 0
      ? [
          {
            key: 'delete',
            icon: 'trash' as const,
            label: t('panel.delete'),
            danger: true,
            run: () => remove(form),
          },
        ]
      : []),
  ]
}

async function add(): Promise<void> {
  const made = await create({})

  if (made) {
    await load()
    edit(made)
  }
}

async function duplicate(form: InboxForm): Promise<void> {
  try {
    const copy = await api.duplicateForm(form.id)
    toast.success(t('panel.duplicated'))
    await load()
    edit(copy)
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(form: InboxForm): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-form-title', { form: name(form) }),
    message: t('panel.delete-form-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeForm(form.id)
    toast.success(t('panel.deleted'))

    if (current.value === form.id) {
      const rest = { ...route.query }
      delete rest.form
      void router.replace({ query: rest })
      open.value = false
    }

    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

/**
 * The whole order, every time.
 *
 * The list is already in its new order on screen — `WxSortableList` reorders the model before
 * it says anything — so this only has to agree with what is there, and a failure has to put
 * the list back rather than leave the screen disagreeing with the database.
 */
async function reorder(): Promise<void> {
  try {
    await api.sortForms(forms.value.map((form) => form.id))
  } catch (error) {
    toast.danger(message(error, t('panel.reorder-failed')))
    await load()
  }
}
</script>

<template>
  <wx-list-screen :title="title" :card="false" fill>
    <template #actions>
      <wx-button
        v-if="canManage"
        variant="outline"
        icon="tag"
        @click="router.push(`${props.base}/statuses`)"
      >
        {{ t('panel.statuses') }}
      </wx-button>
      <wx-button v-if="canManage" type="primary" icon="plus" @click="add">
        {{ t('panel.new-form') }}
      </wx-button>
    </template>

    <wx-card class="wx-inbox" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-inbox__panes"
        :list-width="270"
        :detail-min="420"
        :detail-label="chosen ? name(chosen) : t('panel.forms')"
      >
        <template #list>
          <wx-skeleton v-if="loading" class="wx-inbox__loading" :rows="5" />

          <wx-empty
            v-else-if="forms.length === 0"
            :title="t('panel.no-forms')"
            :description="t('panel.no-forms-help')"
          />

          <!--
            A grip and not the whole row: the row is what opens a form, and a list whose rows
            both open and drag is a list where one of the two happens by accident.
          -->
          <wx-sortable-list
            v-else
            v-model="forms"
            class="wx-inbox__forms"
            plain
            size="sm"
            item-key="id"
            :item-label="name"
            :disabled="!canManage"
            :title="t('panel.forms')"
            @move="reorder"
          >
            <template #default="{ item }">
              <button
                type="button"
                class="wx-inbox-form"
                :class="{ 'is-current': item.id === current }"
                @click="choose(item)"
              >
                <span class="wx-inbox-form__name">
                  <wx-text truncate weight="medium">{{ name(item) }}</wx-text>
                  <wx-badge v-if="!item.is_enabled" type="default">{{ t('panel.off') }}</wx-badge>
                </span>
                <wx-text size="sm" tone="muted" truncate>{{ item.slug }}</wx-text>
              </button>

              <!-- Unread first and in colour; the total behind it, quietly, because it is
                   context rather than work. -->
              <wx-badge
                v-if="item.unread_count"
                type="primary"
                class="wx-inbox-form__count"
                :title="t('panel.unread')"
              >
                {{ item.unread_count }}
              </wx-badge>
              <wx-text
                v-else-if="item.submissions_count"
                size="sm"
                tone="muted"
                :title="t('panel.submissions')"
              >
                {{ item.submissions_count }}
              </wx-text>
            </template>

            <template #actions="{ item }">
              <wx-row-menu :actions="actionsFor(item)" :label="name(item)" />
            </template>
          </wx-sortable-list>
        </template>

        <template #empty>
          <wx-empty :description="t('panel.choose-form')" />
        </template>

        <!--
          The submissions of the chosen form. The pane is the list's, head and all: what is
          above the rows — which form this is, the way back on a phone, the way into what it
          asks — belongs beside the tabs and not above the drawer.
        -->
        <template #detail="{ inline, back }">
          <submission-list
            v-if="chosen"
            :key="chosen.id"
            :form="chosen"
            :base="props.base"
            :inline="inline"
            @back="back"
            @changed="load"
          />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
.wx-inbox {
  height: 100%;
  min-height: 0;
}

/*
 * The card is the screen: what scrolls is inside it, not the page behind it.
 *
 * And the card's corners are the screen's corners. The two panes inside are square and paint
 * their own background right up to the edge, so without the clip they covered the rounding —
 * four white notches poking out of the card, most visible where the column divider and the
 * bottom rule of the list meet it.
 */
.wx-inbox > .wx-card__body {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: inherit;
}

.wx-inbox__panes {
  flex: 1 1 auto;
  min-height: 0;
}

.wx-inbox__loading {
  padding: var(--wx-space-16);
}

.wx-inbox__forms {
  padding: var(--wx-space-8) var(--wx-space-12);
}

/*
 * A plain list draws its rows edge to edge, which is right until one of them is tinted: the
 * highlight then starts exactly at the first letter and ends exactly at the `···`, so the
 * chosen form reads as a stain rather than as a row, and it sits flush against the rule that
 * divides the two columns. The padding is inside the tint, not around it.
 */
.wx-inbox__forms.is-plain .wx-sortable-list__row {
  padding-inline: var(--wx-space-8);
}

/*
 * Air under the rule that carries the column's name.
 *
 * The first row started exactly where the line ended, and the first row here is usually the
 * tinted one — so the chosen form read as hanging off the heading rather than as the first of
 * a list. Visible in the dark theme first, where the tint is a shape of its own.
 */
.wx-inbox__forms.is-plain .wx-sortable-list__head {
  margin-block-end: var(--wx-space-6);
}

.wx-inbox-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  align-items: flex-start;
  /* The row is the target, so it takes the row: a name of four letters should not leave
     three quarters of the line dead. */
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

.wx-inbox-form__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  max-width: 100%;
}

/*
 * The chosen form, marked on the whole row rather than on the name inside it: the row is what
 * was clicked, and a colour on the words alone leaves the grip and the menu looking like they
 * belong to something else. `:has()` because the row is the list's element and the class is
 * ours — the slot cannot reach the element it is drawn into.
 */
.wx-inbox__forms .wx-sortable-list__row:has(.wx-inbox-form.is-current) {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-inbox-form.is-current {
  color: var(--wx-color-primary);
}

.wx-inbox-form__count {
  flex: 0 0 auto;
}
</style>
