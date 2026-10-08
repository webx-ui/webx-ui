<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { confirm, WxAlert, WxButton } from '@webx-ui/core'
import { useDates } from './dates'
import { timeless, type Editing } from './editing'
import { useTranslate } from './i18n'
import type { MergeChoice, MergeConflict } from './merge'

/**
 * What an editor says about other people editing the same record, above its form.
 *
 * Six things, the loudest first. The record was deleted for good, with a way to copy out what was
 * typed. The record is in the bin, with the way out of it. A conflict: both sides changed the same place, and each such
 * place is listed with what it was, what this editor wrote and what the other side wrote, to be
 * settled one by one — everything else has already been merged. A save that came in while this
 * editor was open, with what it changed and an offer to pull it in before saving over it. What
 * others did besides writing — published it, moved it, put an old version back. And,
 * quietest, who else has the record open right now.
 */
const props = defineProps<{ editing: Editing<unknown> }>()

const emit = defineEmits<{
  /** The conflict is settled into the form: save it. */
  save: []
  /** Give up this editor's side and read the record again. */
  theirs: []
  /** Pulled in with nothing overlapping: the form holds both edits now. */
  pulled: []
}>()

const t = useTranslate('webx-admin')
const dates = useDates()

const conflict = computed(() => props.editing.conflict.value)
const incoming = computed(() => props.editing.incoming.value)
const others = computed(() => props.editing.editors.value)

const conflictTitle = computed(() =>
  t('editing.conflict-title', { who: props.editing.who(conflict.value?.theirs.changed) }),
)

/*
 * A conflict is the one thing here that waits for an answer, and it appears after a save — often
 * with the editor scrolled deep into a long page, where the top of the screen is out of sight.
 * Brought into view once, when it appears; the notice of an incoming save is not, because it asks
 * for nothing and pulling the page away from what somebody is reading is worse than missing it.
 */
const conflictAlert = ref<{ $el?: Element } | null>(null)

watch(
  () => conflict.value !== null,
  async (open) => {
    if (!open) return

    await nextTick()
    conflictAlert.value?.$el?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' })
  },
)

function when(at: string | null | undefined): string {
  return at ? dates.short(at) : ''
}

/* What others did besides writing: published, moved, restored an old version. */
const events = computed(() => props.editing.events.value)

/*
 * An old version put back changes every field it touched, and «changed Hero › Eyebrow, Heading,
 * Below the button and more» hides the one thing worth saying: which version, and that it went
 * on the site. When an event explains the change, the notice says the event instead.
 */
const explained = computed(() =>
  events.value.some((event) => event.kind === 'restored_version' || event.kind === 'discarded'),
)

const incomingText = computed(() => {
  const now = incoming.value

  if (!now) return ''

  if (explained.value) return props.editing.describe(events.value)

  return timeless(
    t('editing.incoming', {
      who: props.editing.who(now.theirs.changed),
      what: props.editing.places(now.paths),
      when: when(now.theirs.changed?.at),
    }),
  )
})

const eventsText = computed(() => props.editing.describe(events.value))

/* In the bin: who put it there and when, and the way back for whoever may take it. */
const trashed = computed(() => props.editing.trashed.value)

const trashedTitle = computed(() => {
  const by = props.editing.trashedBy.value

  if (by) return t('editing.trashed-title', { who: props.editing.who(by), when: when(by.at) })

  return t('editing.trashed-anonymous', {
    when: when(props.editing.state.value?.deleted_at),
  })
})

/* Deleted for good: who did it and when. No way back — only the text, to be carried out. */
const gone = computed(() => props.editing.gone.value)

const goneTitle = computed(() => {
  const by = gone.value

  // A 410 with nothing in it — an older server — says only that it is gone.
  if (!by?.at) return t('editing.purged-anonymous')

  return timeless(t('editing.purged-title', { who: props.editing.who(by), when: when(by.at) }))
})

const restoring = ref(false)

/** Out of the bin, and what the form holds saved right after when there is anything. */
async function restore(): Promise<void> {
  const keep = props.editing.unsaved.value

  restoring.value = true

  try {
    if ((await props.editing.restoreFromBin()) && keep) emit('save')
  } finally {
    restoring.value = false
  }
}

function chosen(one: MergeConflict): MergeChoice {
  return conflict.value?.choices[one.id] ?? 'mine'
}

function side(one: MergeConflict, which: 'base' | MergeChoice): string {
  if (which === 'mine' && one.kind === 'removed-mine') return t('editing.removed')
  if (which === 'theirs' && one.kind === 'removed-theirs') return t('editing.removed')

  return props.editing.preview(one[which]) || t('editing.empty')
}

function save(): void {
  props.editing.resolve()
  emit('save')
}

async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('editing.theirs-title'),
    message: t('editing.theirs-text'),
    confirmText: t('editing.take-theirs'),
    cancelText: t('editing.cancel'),
    tone: 'danger',
  })

  if (agreed) emit('theirs')
}

async function pull(): Promise<void> {
  if (await props.editing.pull()) emit('pulled')
}
</script>

<template>
  <!-- Deleted for good: there is nothing to save into and nothing to restore. The form keeps what
       it holds, and the one thing left to offer is carrying it out. -->
  <wx-alert
    v-if="gone"
    class="wx-editing-alerts"
    type="danger"
    :title="goneTitle"
    :description="t('editing.purged-text')"
    live
  >
    <template #actions>
      <wx-button size="sm" type="primary" @click="editing.copyText()">
        {{ t('editing.copy-text') }}
      </wx-button>
    </template>
  </wx-alert>

  <!-- In the bin: nothing typed here can be saved until it is out. The form keeps what it holds,
       so the text can be copied even by somebody who may not restore it. -->
  <wx-alert
    v-else-if="trashed"
    class="wx-editing-alerts"
    type="danger"
    :title="trashedTitle"
    :description="t('editing.trashed-text')"
    live
  >
    <template v-if="editing.canRestore()" #actions>
      <wx-button size="sm" type="primary" :loading="restoring" @click="restore">
        {{ editing.unsaved.value ? t('editing.restore-save') : t('editing.restore-bin') }}
      </wx-button>
    </template>
  </wx-alert>

  <wx-alert
    v-else-if="conflict"
    ref="conflictAlert"
    class="wx-editing-alerts"
    type="warning"
    :title="conflictTitle"
    live
  >
    <p class="wx-editing-alerts__lead">{{ t('editing.conflict-text') }}</p>

    <ul class="wx-editing-alerts__list">
      <li v-for="one in conflict.conflicts" :key="one.id" class="wx-editing-alerts__item">
        <div class="wx-editing-alerts__place">
          {{ editing.label(one.path) }}
          <span v-if="one.kind !== 'changed'" class="wx-editing-alerts__note">
            {{ t(`editing.${one.kind}`) }}
          </span>
        </div>

        <div class="wx-editing-alerts__was">
          <span class="wx-editing-alerts__side">{{ t('editing.base') }}</span>
          {{ side(one, 'base') }}
        </div>

        <div
          class="wx-editing-alerts__choices"
          role="radiogroup"
          :aria-label="editing.label(one.path)"
        >
          <button
            v-for="which in ['mine', 'theirs'] as const"
            :key="which"
            type="button"
            role="radio"
            class="wx-editing-alerts__choice"
            :class="{ 'is-chosen': chosen(one) === which }"
            :aria-checked="chosen(one) === which"
            @click="editing.choose(one.id, which)"
          >
            <span class="wx-editing-alerts__side">{{ t(`editing.${which}`) }}</span>
            <span class="wx-editing-alerts__value">{{ side(one, which) }}</span>
          </button>
        </div>
      </li>
    </ul>

    <template #actions>
      <wx-button size="sm" variant="outline" @click="takeTheirs">
        {{ t('editing.take-theirs') }}
      </wx-button>
      <wx-button size="sm" type="primary" @click="save">
        {{ t('editing.apply') }}
      </wx-button>
    </template>
  </wx-alert>

  <wx-alert v-else-if="incoming" class="wx-editing-alerts" type="info" :title="incomingText" live>
    <template #actions>
      <wx-button size="sm" type="primary" @click="pull">{{ t('editing.pull') }}</wx-button>
    </template>
  </wx-alert>

  <wx-alert
    v-else-if="events.length > 0"
    class="wx-editing-alerts"
    type="info"
    :title="eventsText"
    live
  >
    <template #actions>
      <wx-button size="sm" variant="outline" @click="editing.dismissEvents()">
        {{ t('editing.dismiss') }}
      </wx-button>
    </template>
  </wx-alert>

  <p v-else-if="others.length > 0" class="wx-editing-alerts__others">
    {{ t('editing.others', { names: others.map((one) => one.name).join(', ') }) }}
  </p>
</template>

<style scoped>
.wx-editing-alerts__lead {
  margin: 0 0 var(--wx-space-8);
}

.wx-editing-alerts__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-editing-alerts__item {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-editing-alerts__place {
  font-weight: var(--wx-font-weight-medium);
}

.wx-editing-alerts__note,
.wx-editing-alerts__was,
.wx-editing-alerts__side {
  color: var(--wx-text-muted);
}

.wx-editing-alerts__note {
  margin-inline-start: var(--wx-space-8);
  font-weight: var(--wx-font-weight-regular);
}

.wx-editing-alerts__side {
  margin-inline-end: var(--wx-space-6);
  font-size: var(--wx-font-size-sm);
}

.wx-editing-alerts__choices {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
  gap: var(--wx-space-8);
}

.wx-editing-alerts__choice {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-2);
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-10);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-editing-alerts__choice.is-chosen {
  border-color: var(--wx-border-focus);
  box-shadow: 0 0 0 1px var(--wx-border-focus);
}

.wx-editing-alerts__choice:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 2px;
}

.wx-editing-alerts__value {
  overflow-wrap: anywhere;
}

.wx-editing-alerts__others {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
