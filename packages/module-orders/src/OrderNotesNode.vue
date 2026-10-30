<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef } from 'vue'
import { WxButton, WxCard, WxIcon, WxText, WxTextarea, WxTooltip } from '@webx-ui/core'
import { useTranslate, WxNotesList, type NoteEntry } from '@webx-ui/module-admin'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { authorOf } from './sources'

/**
 * `wx-orders-notes` — «Notes» of the card (decision 90): what one manager left for the next, under
 * the tabs rather than in one of them. A note is written to be found by whoever opens the order
 * next, and a tab of its own is a click they do not know they need to make; under the tabs it is
 * there whichever tab they are on.
 *
 * Over the feed stands the pinned note (decision 91): the order's one standing line — «VIP, call
 * only in the morning» — which the feed, a chronicle, would bury. It is the order's `manager_note`,
 * changed in place by whoever may manage the order and saved on its own, as the contacts are.
 *
 * The feed is the panel's (`WxNotesList`, the same the inbox draws), the notes are the order's:
 * lines of its history of the kind `note` — the panel's, an agent's `orders_note`, a module's —
 * which never change, so there is no menu. Whoever may not manage the order reads them and has no
 * box. The node's public id is `order-notes`.
 */
defineOptions({ name: 'WxOrdersNotes' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)
const draft = ref('')
const sending = ref(false)

const pinned = computed(() => order.value?.manager_note?.trim() || null)
const canPin = computed(() => order.value?.actions.details ?? false)
const editing = ref(false)
const pinDraft = ref('')
const pinning = ref(false)
const editor = useTemplateRef<InstanceType<typeof WxTextarea>>('editor')

const notes = computed<NoteEntry[]>(() =>
  (order.value?.notes ?? []).map((note) => ({
    id: note.id,
    body: note.body,
    created_at: note.created_at,
    author: {
      id: note.author?.id ?? 0,
      name: authorOf(t, note.source, note.author?.name ?? null),
      avatar: note.author?.avatar ?? null,
    },
  })),
)

async function add(body: string): Promise<void> {
  if (!card) return

  sending.value = true

  try {
    if (await card.note(body)) draft.value = ''
  } finally {
    sending.value = false
  }
}

async function editPin(): Promise<void> {
  pinDraft.value = pinned.value ?? ''
  editing.value = true
  await nextTick()
  editor.value?.focus()
}

/** Emptied is unpinned: the plate goes, the history keeps that it was there. */
async function savePin(): Promise<void> {
  if (!card || pinning.value) return

  const text = pinDraft.value.trim()

  if (text === (pinned.value ?? '')) {
    editing.value = false

    return
  }

  pinning.value = true

  try {
    if (await card.pin(text === '' ? null : text)) editing.value = false
  } finally {
    pinning.value = false
  }
}
</script>

<template>
  <wx-card v-if="order" class="wx-orders-notes">
    <div class="wx-orders-notes__body">
      <wx-text weight="medium">{{ panel('notes.title') }}</wx-text>

      <div v-if="editing" class="wx-orders-notes__pin-editor">
        <wx-textarea
          ref="editor"
          v-model="pinDraft"
          :placeholder="t('panel.pin-placeholder')"
          :maxlength="5000"
          :autosize="{ minRows: 2, maxRows: 8 }"
          :aria-label="t('panel.pinned')"
          @keydown.ctrl.enter="savePin"
          @keydown.meta.enter="savePin"
          @keydown.esc="editing = false"
        />
        <wx-text size="sm" tone="muted">{{ t('panel.pin-hint') }}</wx-text>
        <div class="wx-orders-notes__buttons">
          <wx-button size="sm" type="primary" :loading="pinning" @click="savePin">
            {{ t('panel.save') }}
          </wx-button>
          <wx-button size="sm" variant="text" :disabled="pinning" @click="editing = false">
            {{ t('panel.dismiss') }}
          </wx-button>
        </div>
      </div>

      <div v-else-if="pinned" class="wx-orders-notes__pinned">
        <div class="wx-orders-notes__pinned-head">
          <span class="wx-orders-notes__pinned-label">{{ t('panel.pinned') }}</span>
          <wx-tooltip v-if="canPin" :content="t('panel.pin-edit')">
            <button
              class="wx-orders-notes__pencil"
              type="button"
              :aria-label="t('panel.pin-edit')"
              @click="editPin"
            >
              <wx-icon name="edit" size="sm" />
            </button>
          </wx-tooltip>
        </div>
        <!-- Typed, not authored: the line breaks are kept and nothing renders as markup. -->
        <p class="wx-orders-notes__pinned-text">{{ pinned }}</p>
      </div>

      <wx-button
        v-else-if="canPin"
        class="wx-orders-notes__pin"
        variant="text"
        size="sm"
        @click="editPin"
      >
        <template #icon><wx-icon name="plus" /></template>
        {{ t('panel.pin-note') }}
      </wx-button>

      <wx-notes-list
        v-model:draft="draft"
        :notes="notes"
        :can="order.actions.note"
        :sending="sending"
        :maxlength="1000"
        title=""
        @add="add"
      />
    </div>
  </wx-card>
</template>

<style scoped>
.wx-orders-notes__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

/* A plate apart from the feed: the tint and the edge say «stands here», not «was said once». */
.wx-orders-notes__pinned {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  border-inline-start: 3px solid var(--wx-color-warning);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-color-warning-soft);
}

.wx-orders-notes__pinned-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  min-height: 28px;
}

.wx-orders-notes__pinned-label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
}

.wx-orders-notes__pinned-text {
  margin: 0;
  color: var(--wx-text-default);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

/* The pencil of the status strip: an icon the colour of a link, no button around it. */
.wx-orders-notes__pencil {
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

.wx-orders-notes__pencil:hover {
  color: var(--wx-color-primary-hover);
  background: var(--wx-color-primary-soft);
}

.wx-orders-notes__pencil:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-orders-notes__pin {
  align-self: flex-start;
}

.wx-orders-notes__pin-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-orders-notes__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}
</style>
