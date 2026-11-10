<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  WxButton,
  WxCard,
  WxCheckbox,
  WxDescriptions,
  WxDescriptionsItem,
  WxFormItem,
  WxIcon,
  WxInput,
  WxSelect,
  WxText,
  WxTextarea,
  WxTooltip,
} from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { OrderFieldRow } from './types'

/**
 * `wx-orders-fields` — the card «Additional» on the tab «Buyer» (WEBX_UI_ORDER_FIELDS.md §5.4,
 * §6.1): what the buyer answered to the shop's own fields of the checkout, «label — value», and
 * the manager's edit of it.
 *
 * A node of its own, not fields of the screen: the screen is one for every order, while an order
 * may hold a row of a field that is gone, and the rows are the server's to count for each order.
 * The rows are the snapshot's, then the fields switched on the order has no answer to — the
 * manager fills in what the buyer said by phone, or what a one-click order never asked.
 *
 * Saved on its own, as the pinned note is: only the rows changed are sent, nothing here is
 * required, the condition of the checkout does not hold, and the buyer gets no letter. A row of a
 * field that is gone keeps its label: a line or a text still changes, a checkbox and a list only
 * go. Whoever may not manage the order reads the rows and has no pencil. No rows — no card.
 */
defineOptions({ name: 'WxOrdersFields' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)
const rows = computed<OrderFieldRow[]>(() => order.value?.fields ?? [])
const canEdit = computed(() => order.value?.actions.details ?? false)

const editing = ref(false)
const saving = ref(false)
const draft = ref<Record<string, boolean | string>>({})
const errors = ref<Record<string, string[]>>({})

/** What the control of a row holds: a tick, or the text — empty for «nothing». */
function initial(row: OrderFieldRow): boolean | string {
  if (row.type === 'checkbox') return row.value === true

  return typeof row.value === 'string' ? row.value : ''
}

function shown(row: OrderFieldRow): string | null {
  return row.value === null || row.value === false ? null : row.text
}

function optionsOf(row: OrderFieldRow): { value: string; label: string }[] {
  return [{ value: '', label: t('panel.fields-choose') }, ...row.options]
}

function edit(): void {
  draft.value = Object.fromEntries(rows.value.map((row) => [row.key, initial(row)]))
  errors.value = {}
  editing.value = true
}

function errorOf(key: string): string[] | undefined {
  return errors.value[`fields.${key}`]
}

async function save(): Promise<void> {
  if (!card || saving.value) return

  const changed: Record<string, boolean | string | null> = {}

  for (const row of rows.value) {
    const value = draft.value[row.key]

    if (value === undefined) continue

    const typed = typeof value === 'string' ? value.trim() : value

    if (typed !== initial(row)) changed[row.key] = typed === '' || typed === false ? null : typed
  }

  if (Object.keys(changed).length === 0) {
    editing.value = false

    return
  }

  saving.value = true
  errors.value = {}

  try {
    const answer = await card.saveFields(changed)

    if (answer === true) editing.value = false
    else errors.value = answer
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-card
    v-if="order && rows.length > 0"
    class="wx-orders-fields"
    :title="t('panel.fields-title')"
  >
    <template v-if="canEdit && !editing" #extra>
      <wx-tooltip :content="t('panel.fields-edit')">
        <button
          class="wx-orders-fields__pencil"
          type="button"
          :aria-label="t('panel.fields-edit')"
          @click="edit"
        >
          <wx-icon name="edit" size="sm" />
        </button>
      </wx-tooltip>
    </template>

    <div v-if="editing" class="wx-orders-fields__form">
      <wx-text size="sm" tone="muted">{{ t('panel.fields-hint') }}</wx-text>

      <template v-for="row in rows" :key="row.key">
        <wx-form-item
          v-if="row.type === 'checkbox'"
          :error="errorOf(row.key)"
          :help="row.known ? undefined : t('panel.fields-gone')"
        >
          <wx-checkbox
            :model-value="draft[row.key] === true"
            :label="row.label"
            @update:model-value="draft[row.key] = $event === true"
          />
        </wx-form-item>

        <wx-form-item
          v-else
          :label="row.label"
          :error="errorOf(row.key)"
          :help="row.known ? undefined : t('panel.fields-gone')"
        >
          <wx-select
            v-if="row.type === 'select'"
            :model-value="String(draft[row.key] ?? '')"
            :options="optionsOf(row)"
            @update:model-value="draft[row.key] = String($event ?? '')"
          />
          <wx-textarea
            v-else-if="row.type === 'textarea'"
            :model-value="String(draft[row.key] ?? '')"
            :maxlength="2000"
            :autosize="{ minRows: 2, maxRows: 8 }"
            @update:model-value="draft[row.key] = String($event ?? '')"
          />
          <wx-input
            v-else
            :model-value="String(draft[row.key] ?? '')"
            :maxlength="190"
            autocomplete="off"
            @update:model-value="draft[row.key] = String($event ?? '')"
          />
        </wx-form-item>
      </template>

      <div class="wx-orders-fields__buttons">
        <wx-button size="sm" type="primary" :loading="saving" @click="save">
          {{ t('panel.save') }}
        </wx-button>
        <wx-button size="sm" variant="text" :disabled="saving" @click="editing = false">
          {{ t('panel.dismiss') }}
        </wx-button>
      </div>
    </div>

    <wx-descriptions v-else :columns="1" size="sm">
      <wx-descriptions-item v-for="row in rows" :key="row.key" :label="row.label">
        <!-- Typed by the buyer, not authored: the line breaks are kept, nothing renders as markup. -->
        <span v-if="shown(row) !== null" class="wx-orders-fields__value">{{ shown(row) }}</span>
        <span v-else class="wx-orders-fields__empty">{{ t('panel.fields-empty') }}</span>
        <span v-if="!row.known" class="wx-orders-fields__gone">{{ t('panel.fields-gone') }}</span>
      </wx-descriptions-item>
    </wx-descriptions>
  </wx-card>
</template>

<style scoped>
.wx-orders-fields__form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-orders-fields__value {
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.wx-orders-fields__empty,
.wx-orders-fields__gone {
  color: var(--wx-text-muted);
}

.wx-orders-fields__gone {
  display: block;
  font-size: var(--wx-font-size-sm);
}

/* The pencil of the pinned note: an icon the colour of a link, no button around it. */
.wx-orders-fields__pencil {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  inline-size: 28px;
  block-size: 28px;
  padding: 0;
  border: 0;
  border-radius: var(--wx-radius-sm);
  background: transparent;
  color: var(--wx-text-link);
  cursor: pointer;
}

.wx-orders-fields__pencil:hover {
  color: var(--wx-color-primary-hover);
  background: var(--wx-color-primary-soft);
}

.wx-orders-fields__pencil:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-orders-fields__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}
</style>
