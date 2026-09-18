<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  confirm,
  toast,
  WxAvatar,
  WxButton,
  WxEmpty,
  WxSkeleton,
  WxText,
  WxTextarea,
} from '@webx-ui/core'
import DateText from './DateText.vue'
import RowMenu from './RowMenu.vue'
import { useAdmin } from './admin'
import { useErrorText } from './errors'
import { useTranslate } from './i18n'
import { createNotesApi, type EntityNote } from './notes'
import type { RowAction } from './types'

defineOptions({ name: 'WxNotes' })

/**
 * What one administrator wrote on a record for the next one.
 *
 * Deliberately a component of the frame and not of any section. The first place that wanted
 * one was the inbox, the next will be an order and the one after that a client, and three
 * copies of "who may write here, who may edit what they wrote" is two copies too many — so the
 * feed, its address and its rules live here, and a section only says which record it is about.
 *
 * Nothing is drawn while the reader may not write: the server refuses either way, and a box
 * that posts a 403 is worse than no box. Whether they may is the section's answer, because it
 * is the section's permission (`can`).
 */
const props = withDefaults(
  defineProps<{
    /** The alias the server registered the model under — never a class name. */
    type: string
    id: number | string
    /** Whether this reader may write and edit their own. */
    can?: boolean
    /** Heading above the feed; the panel's own word for it by default. */
    title?: string
  }>(),
  { can: true, title: undefined },
)

const emit = defineEmits<{
  /** A note was written, changed or removed — a log beside the feed has to redraw. */
  change: []
}>()

const admin = useAdmin()
const t = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const notes = ref<EntityNote[]>([])
const loading = ref(true)
const draft = ref('')
const sending = ref(false)
const editing = ref<number | null>(null)
const edited = ref('')

const api = computed(() => createNotesApi(admin, props.type, props.id))

const heading = computed(() => props.title ?? t('notes.title'))

async function load(): Promise<void> {
  loading.value = true

  try {
    notes.value = await api.value.list()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

/* The feed follows the record: on this screen the next submission replaces this one in
   place, and a feed that did not notice would be the previous one's notes under a new name. */
watch(
  () => [props.type, props.id] as const,
  () => {
    editing.value = null
    draft.value = ''
    void load()
  },
  { immediate: true },
)

async function add(): Promise<void> {
  const body = draft.value.trim()

  if (body === '' || sending.value) return

  sending.value = true

  try {
    notes.value = [...notes.value, await api.value.add(body)]
    draft.value = ''
    emit('change')
  } catch (error) {
    toast.danger(message(error))
  } finally {
    sending.value = false
  }
}

function edit(note: EntityNote): void {
  editing.value = note.id
  edited.value = note.body
}

async function save(note: EntityNote): Promise<void> {
  const body = edited.value.trim()

  if (body === '') return

  try {
    const saved = await api.value.save(note.id, body)
    notes.value = notes.value.map((one) => (one.id === saved.id ? saved : one))
    editing.value = null
    toast.success(t('notes.saved'))
    emit('change')
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(note: EntityNote): Promise<void> {
  const agreed = await confirm({
    title: t('notes.delete-title'),
    message: t('notes.delete-text'),
    confirmText: t('notes.delete'),
    cancelText: t('notes.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.value.remove(note.id)
    notes.value = notes.value.filter((one) => one.id !== note.id)
    toast.success(t('notes.deleted'))
    emit('change')
  } catch (error) {
    toast.danger(message(error))
  }
}

/** Only the author's own, and only where writing is allowed at all. */
function actionsFor(note: EntityNote): RowAction[] {
  if (!props.can || !note.is_mine) return []

  return [
    { key: 'edit', icon: 'edit', label: t('notes.edit'), run: () => edit(note) },
    {
      key: 'delete',
      icon: 'trash',
      label: t('notes.delete'),
      danger: true,
      run: () => void remove(note),
    },
  ]
}

function who(note: EntityNote): string {
  return note.author?.name ?? t('notes.unknown-author')
}
</script>

<template>
  <section class="wx-notes">
    <wx-text v-if="heading" weight="medium" class="wx-notes__title">{{ heading }}</wx-text>

    <wx-skeleton v-if="loading" :rows="2" />

    <wx-empty v-else-if="notes.length === 0" size="sm" :description="t('notes.empty')" />

    <ul v-else class="wx-notes__list">
      <li v-for="note in notes" :key="note.id" class="wx-note">
        <wx-avatar :name="who(note)" size="sm" tone="auto" />

        <div class="wx-note__body">
          <div class="wx-note__head">
            <wx-text size="sm" weight="medium" truncate>{{ who(note) }}</wx-text>
            <date-text :value="note.created_at" />
            <row-menu :actions="actionsFor(note)" :label="who(note)" />
          </div>

          <template v-if="editing === note.id">
            <wx-textarea v-model="edited" :autosize="{ minRows: 2, maxRows: 10 }" />
            <div class="wx-note__buttons">
              <wx-button size="sm" type="primary" @click="save(note)">
                {{ t('notes.save') }}
              </wx-button>
              <wx-button size="sm" variant="text" @click="editing = null">
                {{ t('notes.cancel') }}
              </wx-button>
            </div>
          </template>

          <!-- `white-space: pre-wrap` and nothing else: a note is typed, not authored, and
               everything that renders markup here is a way to put a link nobody wrote into a
               panel. -->
          <p v-else class="wx-note__text">{{ note.body }}</p>
        </div>
      </li>
    </ul>

    <div v-if="can" class="wx-notes__new">
      <wx-textarea
        v-model="draft"
        :placeholder="t('notes.placeholder')"
        :autosize="{ minRows: 2, maxRows: 8 }"
      />
      <wx-button
        size="sm"
        type="primary"
        :disabled="draft.trim() === ''"
        :loading="sending"
        @click="add"
      >
        {{ t('notes.add') }}
      </wx-button>
    </div>
  </section>
</template>

<style scoped>
.wx-notes {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  /* The feed decides its own layout from its own width and not the window's: it hangs in a
     card beside other cards, and the window knows nothing about how wide that is. */
  container-type: inline-size;
}

.wx-notes__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-note {
  display: flex;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-note__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  flex: 1 1 auto;
  min-width: 0;
}

/* The menu keeps the end of the line, so a note by somebody with a long name and one by
   somebody with a short one still have their controls in the same place. */
.wx-note__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-note__head > :last-child {
  margin-inline-start: auto;
}

.wx-note__text {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-default);
}

.wx-note__buttons,
.wx-notes__new {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

.wx-notes__new > :first-child {
  flex: 1 1 auto;
  min-width: 0;
}

/* Where the box and the button will not sit side by side without squeezing the box to three
   words a line, the button goes under it. */
@container (max-width: 440px) {
  .wx-note__buttons,
  .wx-notes__new {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
