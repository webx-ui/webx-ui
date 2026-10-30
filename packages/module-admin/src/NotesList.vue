<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxAvatar, WxButton, WxEmpty, WxSkeleton, WxText, WxTextarea } from '@webx-ui/core'
import DateText from './DateText.vue'
import RowMenu from './RowMenu.vue'
import { useAdmin } from './admin'
import { useAvatars } from './avatars'
import { useTranslate } from './i18n'
import type { NoteEntry } from './notes'
import type { RowAction } from './types'

defineOptions({ name: 'WxNotesList' })

/**
 * The notes of a record as the panel draws them — the feed, the empty state, the box and the
 * button — and nothing about where they are kept.
 *
 * Two kinds of record write notes, and they keep them apart: a submission in the panel's own
 * table (`WxNotes` reads it), an order in its history, where a note is one more line that never
 * changes. What they share is what the manager sees, so that is the part that lives here; the
 * section reads, posts, and hands the result back.
 *
 * A note is edited and removed only where `editable` says the store allows it, and only by its
 * author — the server's `is_mine`.
 */
const props = withDefaults(
  defineProps<{
    notes: NoteEntry[]
    /** Whether this reader may write — and, with `editable`, change their own. */
    can?: boolean
    /** Whether a note can be changed once written at all. */
    editable?: boolean
    loading?: boolean
    /** A note is on its way: the button spins and a second one is not sent. */
    sending?: boolean
    /** Heading above the feed; the panel's own word by default, `''` for none (a tab is one). */
    title?: string
    /** The longest note the store takes; the box stops there. */
    maxlength?: number
  }>(),
  {
    can: true,
    editable: false,
    loading: false,
    sending: false,
    title: undefined,
    maxlength: undefined,
  },
)

/** What is being typed — bound by the section, which empties it once the note is kept. */
const draft = defineModel<string>('draft', { default: '' })
/** The note open for editing — bound by the section, which closes it once the change is kept. */
const editing = defineModel<number | string | null>('editing', { default: null })

const emit = defineEmits<{
  add: [body: string]
  save: [note: NoteEntry, body: string]
  remove: [note: NoteEntry]
}>()

const admin = useAdmin()
const t = useTranslate('webx-admin')
const avatars = useAvatars(admin, () => props.notes.map((note) => note.author?.avatar))
const edited = ref('')

const heading = computed(() => props.title ?? t('notes.title'))

watch(editing, (id) => {
  edited.value = props.notes.find((note) => note.id === id)?.body ?? ''
})

function add(): void {
  const body = draft.value.trim()

  if (body === '' || props.sending) return

  emit('add', body)
}

function save(note: NoteEntry): void {
  const body = edited.value.trim()

  if (body === '') return

  emit('save', note, body)
}

/** Only the author's own, and only where the store lets a note change. */
function actionsFor(note: NoteEntry): RowAction[] {
  if (!props.editable || !props.can || !note.is_mine) return []

  return [
    { key: 'edit', icon: 'edit', label: t('notes.edit'), run: () => (editing.value = note.id) },
    {
      key: 'delete',
      icon: 'trash',
      label: t('notes.delete'),
      danger: true,
      run: () => emit('remove', note),
    },
  ]
}

function who(note: NoteEntry): string {
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
        <wx-avatar
          :name="who(note)"
          :src="avatars.src(note.author?.avatar)"
          size="sm"
          tone="auto"
        />

        <div class="wx-note__body">
          <div class="wx-note__head">
            <wx-text size="sm" weight="medium" truncate>{{ who(note) }}</wx-text>
            <date-text :value="note.created_at" />
            <row-menu :actions="actionsFor(note)" :label="who(note)" />
          </div>

          <template v-if="editing === note.id">
            <wx-textarea
              v-model="edited"
              :maxlength="maxlength"
              :autosize="{ minRows: 2, maxRows: 10 }"
            />
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
        :maxlength="maxlength"
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

.wx-note__buttons {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

/*
 * The button stands under the box, not beside it.
 *
 * Beside it, the button took its own width off the box at every width, so the thing being
 * written was always narrower than the room there was for it — and the two lined up along
 * their tops, which put a button at the height of the first line of a note that had not been
 * typed yet. Under it, the box is the width of the card and the button is the width of its
 * own word.
 */
.wx-notes__new {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: var(--wx-space-8);
}

.wx-notes__new > :first-child {
  min-width: 0;
}

.wx-notes__new > :last-child {
  align-self: flex-start;
}

/* On a phone it takes the width instead: a target for a finger, and there is nothing beside
   it to line up with anyway. */
@container (max-width: 440px) {
  .wx-note__buttons {
    flex-direction: column;
    align-items: stretch;
  }

  .wx-notes__new > :last-child {
    align-self: stretch;
  }
}
</style>
