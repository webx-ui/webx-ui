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
import { createInboxApi } from './api'
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

const edit = createModal<InboxField, { field: InboxField | null; form: InboxForm }>(FieldDialog)

function name(field: InboxField): string {
  return localizedValue(field.title, locales.active.value, field.key)
}

/** What the row says about itself, under its name: the type, then what is true of it. */
function marks(field: InboxField): string[] {
  return [
    t(`fields.type-${field.type}`),
    ...(field.is_required ? [t('fields.is-required')] : []),
    ...(field.in_table ? [t('fields.in-table')] : []),
    ...(field.is_fullsize ? [] : [t('fields.is-fullsize')]),
  ]
}

const empty = computed(() => fields.value.length === 0)

async function open(field: InboxField | null): Promise<void> {
  const saved = await edit({ field, form: props.form })

  if (!saved) return

  fields.value =
    field === null
      ? [...fields.value, saved]
      : fields.value.map((one) => (one.id === saved.id ? saved : one))
}

async function remove(field: InboxField): Promise<void> {
  const agreed = await confirm({
    title: t('fields.delete-title', { field: name(field) }),
    message: t('fields.delete-text'),
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
    { key: 'edit', icon: 'edit', label: t('fields.edit-field'), run: () => void open(field) },
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
    toast.danger(message(error, t('panel.reorder-failed')))
  }
}
</script>

<template>
  <wx-card :title="t('panel.tab-fields')">
    <template v-if="canManage" #extra>
      <wx-button type="primary" size="sm" @click="open(null)">
        <template #icon><wx-icon name="plus" /></template>
        {{ t('fields.new-field') }}
      </wx-button>
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
      :disabled="!canManage"
      :aria-label="t('panel.tab-fields')"
      @move="reorder"
    >
      <template #default="{ item }">
        <div class="wx-inbox-field">
          <div class="wx-inbox-field__name">
            <wx-text weight="medium" truncate>{{ name(item) }}</wx-text>
            <wx-badge v-if="!item.is_enabled" type="default">{{ t('panel.off') }}</wx-badge>
          </div>
          <wx-text size="sm" tone="muted" truncate>
            <code>fields[{{ item.key }}]</code> · {{ marks(item).join(' · ') }}
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
.wx-inbox-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  flex: 1 1 auto;
  min-width: 0;
}

.wx-inbox-field__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}
</style>
