<script setup lang="ts">
import { computed } from 'vue'
import {
  useAdmin,
  useErrorText,
  useTranslate,
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
  WxIcon,
  WxCard,
  WxEmpty,
  WxSortableList,
  WxText,
} from '@webx-ui/core'
import FieldDialog from './FieldDialog.vue'
import { conditionOf, describeCondition, problemsOf, type ConditionProblem } from './conditions'
import { createInboxApi } from './api'
import { emptySteps, insideStep, isStep, MAX_STEPS, stepCount, stepNumbers } from './steps'
import { useInboxMessages } from './i18n'
import type { InboxField, InboxForm } from './types'

/**
 * The questions the form asks, in the order it asks them.
 *
 * A list with a grip rather than a table, because the order is the thing being edited here
 * and a table cannot be dragged. Each question is saved on its own, in its own dialog: a
 * field is a row with an identity that answers point at (§2.1), not a value of the form.
 */
const props = defineProps<{ form: InboxForm; canManage: boolean }>()

const fields = defineModel<InboxField[]>({ required: true })

const context = useAdmin()
const api = createInboxApi(context)
const locales = useLocales()
useInboxMessages()

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const edit = createModal<
  InboxField,
  {
    field: InboxField | null
    form: InboxForm
    taken: string[]
    fields: InboxField[]
    step?: boolean
  }
>(FieldDialog)

function name(field: InboxField): string {
  return localizedValue(field.title, locales.active.value, field.key)
}

/** What the row says about itself, under its name: the type, then what is true of it. */
function marks(field: InboxField): string[] {
  if (isStep(field)) return []

  return [
    t(`fields.type-${field.type}`),
    ...(field.is_required ? [t('fields.is-required')] : []),
    ...(field.in_table ? [t('fields.in-table')] : []),
    ...(field.is_fullsize ? [] : [t('fields.is-fullsize')]),
  ]
}

/** «if Purpose = Agency, Pre-register»: a field not always asked says when it is. */
function shownBy(field: InboxField): string | null {
  const ticked = t('fields.condition-ticked')
  const parts = (['when', 'unless'] as const).flatMap((key) => {
    const condition = conditionOf(field.options, key)

    return condition === null
      ? []
      : [
          t(`fields.shown-${key}`, {
            condition: describeCondition(condition, fields.value, locales.active.value, ticked),
          }),
        ]
  })

  return parts.length === 0 ? null : parts.join('; ')
}

/*
 * What the server would refuse about a row's conditions, said on the row itself: a field that
 * was moved, renamed past or lost a choice before this check existed — or edited through an
 * agent — would otherwise only show up as a dialog that will not save.
 */
function problemText(problem: ConditionProblem): string {
  const source = fields.value.find((one) => one.key === problem.name)

  return t(`fields.problem-${problem.code.replace('_', '-')}`, {
    field: source ? name(source) : problem.name,
    value: problem.value ?? '',
  })
}

const problems = computed(
  () =>
    new Map(
      fields.value.map((field) => [field.id, problemsOf(field, fields.value).map(problemText)]),
    ),
)

const empty = computed(() => fields.value.length === 0)

/*
 * The steps (FORM_STEPS §9.1): a step row is a heading of the list, dragged like a field — it moves
 * the border between two steps, not the fields under it. Numbered as the site counts them, the
 * fields above the first row being step 1; one with no field under it says so, the site skips it.
 */
const numbers = computed(() => stepNumbers(fields.value))
const emptyRows = computed(() => emptySteps(fields.value))
const hasSteps = computed(() => numbers.value.size > 0)
/* «+ Step» on an empty form would be a step of nothing; past ten the indicator does not fit. */
const canAddStep = computed(
  () => !empty.value && stepCount(fields.value) + (hasSteps.value ? 1 : 2) <= MAX_STEPS,
)

function stepHeading(field: InboxField): string {
  return t('fields.step-numbered', { number: numbers.value.get(field.id) ?? 1, title: name(field) })
}

async function open(field: InboxField | null, step = false): Promise<void> {
  // The names the other fields answer to: a new one made out of its label must not be one of them.
  const taken = fields.value.filter((one) => one.id !== field?.id).map((one) => one.key)
  const saved = await edit({ field, form: props.form, taken, fields: fields.value, step })

  if (!saved) return

  fields.value =
    field === null
      ? [...fields.value, saved]
      : fields.value.map((one) => (one.id === saved.id ? saved : one))
}

async function remove(field: InboxField): Promise<void> {
  const agreed = await confirm({
    title: t('fields.delete-title', { field: name(field) }),
    message: isStep(field) ? t('fields.delete-step-text') : t('fields.delete-text'),
    confirmText: t('fields.delete'),
    cancelText: t('fields.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeField(field.id)
    fields.value = fields.value.filter((one) => one.id !== field.id)
    toast.success(t('panel.deleted'))
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(field: InboxField): RowAction[] {
  if (!props.canManage) return []

  return [
    { key: 'edit', icon: 'edit', label: t('fields.edit'), run: () => void open(field) },
    {
      key: 'delete',
      icon: 'trash',
      label: t('fields.delete'),
      danger: true,
      run: () => void remove(field),
    },
  ]
}

/** The whole order, every time — the list is already in it on screen. */
async function reorder(): Promise<void> {
  try {
    await api.sortFields(
      props.form.id,
      fields.value.map((field) => field.id),
    )
  } catch (error) {
    // Refused — a field put above the one it counts on: the list goes back to what is saved.
    toast.danger(message(error, t('panel.reorder-failed')))
    fields.value = await api.fields(props.form.id).catch(() => fields.value)
  }
}
</script>

<template>
  <wx-card :title="t('panel.tab-fields')">
    <template v-if="canManage" #extra>
      <div class="wx-inbox-fields__add">
        <wx-button variant="outline" size="sm" :disabled="!canAddStep" @click="open(null, true)">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('fields.add-step') }}
        </wx-button>
        <wx-button type="primary" size="sm" @click="open(null)">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('fields.new-field') }}
        </wx-button>
      </div>
    </template>

    <wx-empty
      v-if="empty"
      :title="t('fields.no-fields')"
      :description="t('fields.no-fields-help')"
    />

    <wx-sortable-list
      v-else
      v-model="fields"
      plain
      item-key="id"
      :item-label="name"
      :drag-label="t('panel.reorder')"
      :disabled="!canManage"
      :aria-label="t('panel.tab-fields')"
      @move="reorder"
    >
      <template #default="{ item }">
        <div v-if="isStep(item)" class="wx-inbox-field is-step">
          <div class="wx-inbox-field__name">
            <wx-icon name="list" size="sm" aria-hidden="true" />
            <wx-text weight="semibold" truncate>{{ stepHeading(item) }}</wx-text>
            <wx-badge v-if="!item.is_enabled" type="default">{{ t('panel.off') }}</wx-badge>
            <wx-badge v-if="emptyRows.has(item.id)" type="warning">
              {{ t('fields.step-empty-badge') }}
            </wx-badge>
            <wx-badge v-if="problems.get(item.id)?.length" type="danger">
              {{ t('fields.condition-broken') }}
            </wx-badge>
          </div>
          <wx-text v-if="emptyRows.has(item.id)" size="sm" tone="warning">
            {{ t('fields.step-empty') }}
          </wx-text>
          <wx-text v-if="shownBy(item)" size="sm" tone="muted" class="wx-inbox-field__condition">
            <wx-icon name="eye" size="sm" aria-hidden="true" />
            <span>{{ shownBy(item) }}</span>
          </wx-text>
          <wx-text
            v-for="problem in problems.get(item.id) ?? []"
            :key="problem"
            size="sm"
            tone="danger"
          >
            {{ problem }}
          </wx-text>
        </div>
        <div v-else :class="['wx-inbox-field', { 'is-inside': insideStep(fields, item) }]">
          <div class="wx-inbox-field__name">
            <wx-text weight="medium" truncate>{{ name(item) }}</wx-text>
            <wx-badge v-if="!item.is_enabled" type="default">{{ t('panel.off') }}</wx-badge>
            <wx-badge v-if="problems.get(item.id)?.length" type="danger">
              {{ t('fields.condition-broken') }}
            </wx-badge>
          </div>
          <wx-text size="sm" tone="muted" truncate>
            <!-- A field nobody named answers to its row number, which means nothing to anybody
                 reading the list: it shows no name rather than that one. -->
            <template v-if="item.name"
              ><code>{{ item.name }}</code> · </template
            >{{ marks(item).join(' · ') }}
          </wx-text>
          <wx-text v-if="shownBy(item)" size="sm" tone="muted" class="wx-inbox-field__condition">
            <wx-icon name="eye" size="sm" aria-hidden="true" />
            <span>{{ shownBy(item) }}</span>
          </wx-text>
          <wx-text
            v-for="problem in problems.get(item.id) ?? []"
            :key="problem"
            size="sm"
            tone="danger"
          >
            {{ problem }}
          </wx-text>
        </div>
      </template>

      <template #actions="{ item }">
        <wx-row-menu :actions="actionsFor(item)" :label="name(item)" />
      </template>
    </wx-sortable-list>
  </wx-card>
</template>

<style scoped>
.wx-inbox-fields__add {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

/* A step row is a heading of the list; the fields under it stand a little in from it. */
.wx-inbox-field.is-step {
  padding-block: var(--wx-space-4);
}

.wx-inbox-field.is-inside {
  padding-inline-start: var(--wx-space-16);
}

.wx-inbox-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  flex: 1 1 auto;
  min-width: 0;
}

.wx-inbox-field__condition {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-inbox-field__condition > span {
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-inbox-field__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}
</style>
