<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
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
  localizedValue,
  toast,
  useLocales,
  WxAction,
  WxButton,
  WxCard,
  WxEmpty,
  WxIcon,
  WxIndicator,
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
 * Which of the two is the screen and which is the chooser is the second decision. The forms
 * are the chooser: they are the `filters` column of the pane, so on a phone they fold into a
 * panel and the submissions are what the section opens on. The other way round — forms as the
 * list, submissions as the record beside it — is what this was, and it cost the reader a
 * screen: somebody who opens the section is asking what came in, and was shown a list of
 * three form names instead, every time.
 *
 * One form is therefore always open (the first, unless the address names another), and what
 * is here is the column: the forms, in the order somebody dragged them, with what is waiting
 * in each.
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

/** The list of submissions, for the one action of its own that the section's head carries. */
const pane = useTemplateRef<{ create: () => void }>('pane')

/**
 * Whether the forms still have a column, and whether their panel is up.
 *
 * Both live here rather than inside the pane because the way to the forms is in the head of
 * the section, beside its other actions — a button that appears exactly when the column is
 * gone. The pane says when that is.
 */
const columnInline = ref(true)
const formsOpen = ref(false)

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

/**
 * One form is always open, and the address always says which.
 *
 * The section is the submissions, so the first form stands in until somebody picks another —
 * on a phone especially, where the alternative is opening the panel before seeing anything at
 * all. It covers the address that names no form, the address that names one that has since
 * been deleted, and the list arriving after the address did; `replace`, because none of the
 * three is a place worth going back to.
 */
watch([forms, chosen], () => {
  if (forms.value.length === 0 || chosen.value !== null) return

  void router.replace({ query: { ...route.query, form: String(forms.value[0].id) } })
})

function choose(form: InboxForm, close: () => void): void {
  void router.replace({ query: { ...route.query, form: String(form.id) } })
  /* Picked from the panel: the panel has done its job. It is a column on a wide screen, and
     closing what is not open does nothing there. */
  close()
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

    /* The address named the form that is gone; what stands in its place is decided above. */
    if (current.value === form.id) {
      const rest = { ...route.query }
      delete rest.form
      void router.replace({ query: rest })
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

/**
 * What the section offers. Declared, because on a phone the head folds it into the ···.
 *
 * The one it exists for is a submission, not a form: this is the inbox, and the section is
 * opened dozens of times for what came in for every once that a form is added. A new form is
 * the `+` over the list of forms — beside the thing it makes one more of — and what used to
 * stand here as the primary button was that, which is why it was the biggest thing on the
 * screen and almost never what anybody wanted.
 */
const actions = computed<ScreenAction[]>(() => {
  const list: ScreenAction[] = []

  if (canManage.value) {
    list.push({
      key: 'statuses',
      label: t('panel.statuses'),
      icon: 'tag',
      run: () => void router.push(`${props.base}/statuses`),
    })
  }

  /* The dialog belongs to the list: what is written has to land in it, in the filter that is
     on, and be counted in its tabs. The head only asks for it. */
  if (context.can('inbox.update') && chosen.value !== null) {
    list.push({
      key: 'new-submission',
      label: t('panel.new-submission'),
      icon: 'plus',
      primary: true,
      run: () => void pane.value?.create(),
    })
  }

  return list
})
</script>

<template>
  <wx-list-screen :title="title" :actions="actions" :card="false">
    <!--
      Written here rather than declared as an action, because an action folds behind the ···
      on a narrow head and this is only ever drawn on one: it is the way to the forms for a
      reader whose column of them is gone. It stands where the other actions of the section
      stand, beside the one that writes a submission.
    -->
    <template v-if="!columnInline" #actions>
      <wx-button variant="outline" @click="formsOpen = true">
        <template #icon><wx-icon name="list" /></template>
        {{ t('panel.forms') }}
      </wx-button>
    </template>

    <wx-card class="wx-inbox" padding="none">
      <wx-list-detail
        v-model:filters-open="formsOpen"
        class="wx-inbox__panes"
        :filters-width="270"
        :detail-min="420"
        :filters-title="t('panel.forms')"
        @filters-inline="columnInline = $event"
      >
        <!--
          The forms are what narrows the list, so they are the column that folds: on a phone
          this is a panel raised from the head of the submissions, and the submissions are
          the screen.
        -->
        <template #filters="{ inline, close }">
          <wx-skeleton v-if="loading" class="wx-inbox__loading" :rows="5" />

          <template v-else>
            <!--
              In the panel the `+` would stand alone on a line of its own under the drawer's
              own heading, which reads as a stray mark rather than as a control. There is
              room for the word here, so it takes it.
            -->
            <wx-button
              v-if="!inline && canManage"
              class="wx-inbox__new-form"
              variant="outline"
              block
              @click="add"
            >
              <template #icon><wx-icon name="plus" /></template>
              {{ t('panel.new-form') }}
            </wx-button>

            <!--
              A grip and not the whole row: the row is what opens a form, and a list whose
              rows both open and drag is a list where one of the two happens by accident.
            -->
            <wx-sortable-list
              v-model="forms"
              class="wx-inbox__forms"
              :class="{ 'is-panel': !inline }"
              plain
              size="sm"
              item-key="id"
              :item-label="name"
              :disabled="!canManage"
              :title="inline ? t('panel.forms') : undefined"
              @move="reorder"
            >
              <!--
                A new form is made where the forms are and not in the head of the section: it
                is one more of these, and it is asked for once for every few dozen times
                somebody opens the inbox to read what came in. An icon, because the word for
                it is already written beside it.
              -->
              <template v-if="inline && canManage" #extra>
                <wx-action icon="plus" size="sm" :title="t('panel.new-form')" @click="add" />
              </template>

              <template #default="{ item }">
                <button
                  type="button"
                  class="wx-inbox-form"
                  :class="{ 'is-current': item.id === current, 'is-off': !item.is_enabled }"
                  @click="choose(item, close)"
                >
                  <!--
                    A switched-off form is said by the name itself — struck through and grey —
                    rather than by a badge beside it. The badge was a second thing on a line
                    270px wide that already holds a name, a count and the ···: it did not
                    shrink, so it ran under the menu, and it said in a word what the type says
                    at a glance.
                  -->
                  <span class="wx-inbox-form__name">
                    <wx-text truncate weight="medium" :tone="item.is_enabled ? 'default' : 'muted'">
                      {{ name(item) }}
                    </wx-text>

                    <!--
                      On the name's line, not under it. The list stacks what the slot hands it,
                      so a count standing beside the form was a third line under the address —
                      the row grew by a line to say a single digit. Unread first and in colour;
                      the total behind it, quietly, because it is context rather than work.
                    -->
                    <wx-indicator
                      v-if="item.unread_count"
                      class="wx-inbox-form__count"
                      type="primary"
                      :value="item.unread_count"
                      :label="t('panel.unread')"
                    />
                    <wx-indicator
                      v-else-if="item.submissions_count"
                      class="wx-inbox-form__count"
                      type="neutral"
                      :value="item.submissions_count"
                      :label="t('panel.submissions')"
                    />
                  </span>
                  <wx-text size="sm" tone="muted" truncate>{{ item.slug }}</wx-text>
                </button>
              </template>

              <template #actions="{ item }">
                <wx-row-menu :actions="actionsFor(item)" :label="name(item)" />
              </template>
            </wx-sortable-list>
          </template>
        </template>

        <!--
          The submissions of the chosen form, and the screen. The pane is the list's, head
          and all: what is above the rows — which form this is, the way to another one where
          there is no column for them — belongs beside the tabs.

          Nothing chosen and nothing loading means the site has no forms yet, since a form
          that exists is picked for the reader. The word about it goes here rather than in
          the column, because here is what a phone shows.
        -->
        <template #list>
          <wx-skeleton v-if="loading" class="wx-inbox__loading" :rows="6" />

          <submission-list
            v-else-if="chosen"
            ref="pane"
            :key="chosen.id"
            :form="chosen"
            :base="props.base"
            @changed="load"
          />

          <wx-empty v-else :title="t('panel.no-forms')" :description="t('panel.no-forms-help')" />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
/*
 * What scrolls here is the page.
 *
 * The section used to be as tall as the window, with the rows scrolling inside a box of their
 * own: a second scroller inside the first, its bar running down the middle of the screen, and
 * a wheel that meant one thing over the rows and another an inch to the left. Page after page
 * of the panel scrolls as a page; this one now does too, and the card is as tall as what is
 * in it.
 *
 * The card's corners are still the screen's corners. The two panes inside are square and paint
 * their own background right up to the edge, so without the clip they covered the rounding —
 * four white notches poking out of the card, most visible where the column divider and the
 * bottom rule of the list meet it. `clip` rather than `hidden`: `hidden` would make this a
 * scroll container again, and everything sticky inside would pin itself to a box that never
 * moves (CLAUDE.md §4).
 */
.wx-inbox > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}

.wx-inbox__loading {
  padding: var(--wx-space-16);
}

.wx-inbox__forms {
  padding: var(--wx-space-8) var(--wx-space-12);
}

/* In the panel the sheet is the column, and it insets what it holds already: a second step
   inside the first stands the same list further from the edge than it stands anywhere else
   (CLAUDE.md §4). */
.wx-inbox__forms.is-panel {
  padding: 0;
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
 * Air under the rule that carries the column's name, and the rows' own step beside it.
 *
 * The first row started exactly where the line ended, and the first row here is usually the
 * tinted one — so the chosen form read as hanging off the heading rather than as the first of
 * a list. Visible in the dark theme first, where the tint is a shape of its own.
 *
 * The step is the one the rows take inside their tint (above), so the name of the column
 * begins where the names of the forms do and the `+` stands over the row of `···` rather
 * than eight pixels to the right of them. Measured: 338 against 330.
 */
.wx-inbox__forms.is-plain .wx-sortable-list__head {
  margin-block-end: var(--wx-space-6);
  padding-inline: var(--wx-space-8);
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
 * Switched off: struck through and grey. On the name alone and not on the line it stands in,
 * because what is off is the form — the count beside it is still true, and a decoration set
 * on the row would run through that too and could not be taken off a child. The grey comes
 * from the text's own `tone`, since `WxText` declares its colour on its own element and an
 * inherited one would never reach it (CLAUDE.md §4).
 */
.wx-inbox-form.is-off .wx-inbox-form__name > .wx-text {
  text-decoration: line-through;
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
