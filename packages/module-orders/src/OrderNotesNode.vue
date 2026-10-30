<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxCard } from '@webx-ui/core'
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
 * The feed is the panel's (`WxNotesList`, the same the inbox draws), the notes are the order's:
 * lines of its history of the kind `note` — the panel's, an agent's `orders_note`, a module's —
 * which never change, so there is no menu. Whoever may not manage the order reads them and has no
 * box. The node's public id is `order-notes`.
 */
defineOptions({ name: 'WxOrdersNotes' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)
const draft = ref('')
const sending = ref(false)

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
</script>

<template>
  <wx-card v-if="order" class="wx-orders-notes">
    <wx-notes-list
      v-model:draft="draft"
      :notes="notes"
      :can="order.actions.note"
      :sending="sending"
      :maxlength="1000"
      @add="add"
    />
  </wx-card>
</template>
