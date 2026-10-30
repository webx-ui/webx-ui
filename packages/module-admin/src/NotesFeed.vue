<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { confirm, toast } from '@webx-ui/core'
import NotesList from './NotesList.vue'
import { useAdmin } from './admin'
import { useErrorText } from './errors'
import { useTranslate } from './i18n'
import { createNotesApi, type EntityNote, type NoteEntry } from './notes'

defineOptions({ name: 'WxNotes' })

/**
 * What one administrator wrote on a record for the next one, kept in the panel's own table.
 *
 * Deliberately a component of the frame and not of any section: "who may write here, who may
 * edit what they wrote" is a rule, and one copy of it is enough — so the address and its rules
 * live here, and a section only says which record it is about. What the feed looks like is
 * `WxNotesList`, which an order draws from its own history.
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
const editing = ref<number | string | null>(null)

const api = computed(() => createNotesApi(admin, props.type, props.id))

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

async function add(body: string): Promise<void> {
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

async function save(note: NoteEntry, body: string): Promise<void> {
  try {
    const saved = await api.value.save(Number(note.id), body)
    notes.value = notes.value.map((one) => (one.id === saved.id ? saved : one))
    editing.value = null
    toast.success(t('notes.saved'))
    emit('change')
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(note: NoteEntry): Promise<void> {
  const agreed = await confirm({
    title: t('notes.delete-title'),
    message: t('notes.delete-text'),
    confirmText: t('notes.delete'),
    cancelText: t('notes.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.value.remove(Number(note.id))
    notes.value = notes.value.filter((one) => one.id !== note.id)
    toast.success(t('notes.deleted'))
    emit('change')
  } catch (error) {
    toast.danger(message(error))
  }
}
</script>

<template>
  <notes-list
    v-model:draft="draft"
    v-model:editing="editing"
    :notes="notes"
    :can="can"
    :title="title"
    :loading="loading"
    :sending="sending"
    editable
    @add="add"
    @save="save"
    @remove="remove"
  />
</template>
