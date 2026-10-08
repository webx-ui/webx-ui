<script setup lang="ts">
import { computed } from 'vue'
import { confirm, WxAlert, WxButton } from '@webx-ui/core'
import { useDates } from './dates'
import type { Editing } from './editing'
import { useTranslate } from './i18n'
import type { MergeChoice, MergeConflict } from './merge'

/**
 * What an editor says about other people editing the same record, above its form.
 *
 * Three things, the loudest first. A conflict: both sides changed the same place, and each such
 * place is listed with what it was, what this editor wrote and what the other side wrote, to be
 * settled one by one — everything else has already been merged. A save that came in while this
 * editor was open, with what it changed and an offer to pull it in before saving over it. And,
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

function when(at: string | null | undefined): string {
  return at ? dates.short(at) : ''
}

const incomingText = computed(() => {
  const now = incoming.value

  if (!now) return ''

  const labels = [...new Set(now.paths.map((path) => props.editing.label(path)))]
  const what = labels.slice(0, 3).join(', ')
  const key = labels.length > 3 ? 'editing.incoming-more' : 'editing.incoming'

  return t(key, {
    who: props.editing.who(now.theirs.changed),
    what: what || t('editing.order'),
    when: when(now.theirs.changed?.at),
  })
})

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
    confirmText: t('editing.theirs'),
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
  <wx-alert
    v-if="conflict"
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

        <div class="wx-editing-alerts__choices" role="radiogroup" :aria-label="editing.label(one.path)">
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
        {{ t('editing.theirs') }}
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
