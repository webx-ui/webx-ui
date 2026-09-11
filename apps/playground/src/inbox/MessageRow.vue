<script setup lang="ts">
import { computed } from 'vue'
import { forms, type Submission } from './data'

const props = defineProps<{
  item: Submission
  selected: boolean
}>()

defineEmits<{ open: [] }>()

const form = computed(() => forms.find((f) => f.id === props.item.form) ?? forms[0])

/* The second line of a row: where it came from, and what state it is in. */
const state = computed(() => {
  if (props.item.status === 'done') return { text: 'оброблено', tone: 'done' as const }
  if (props.item.status === 'progress') return { text: 'в роботі', tone: 'progress' as const }
  return { text: props.item.phone, tone: 'phone' as const }
})
</script>

<template>
  <wx-entity-card
    class="row"
    :class="{ 'row--unread': item.unread, 'row--done': item.status === 'done' }"
    variant="plain"
    size="sm"
    :selected="selected"
    @click="$emit('open')"
  >
    <template #media>
      <span class="row__avatar">{{ item.initials }}</span>
    </template>

    <template #title>
      <span class="row__head">
        <span class="row__name">{{ item.name }}</span>
        <span class="row__time" :title="item.at">{{ item.time }}</span>
      </span>
    </template>

    <template #meta>
      <span class="row__meta">
        <wx-badge :type="form.tone" size="sm">{{ form.label }}</wx-badge>

        <span v-if="state.tone === 'phone'" class="row__muted">{{ state.text }}</span>

        <span v-else-if="state.tone === 'progress'" class="row__muted">
          <span class="row__assignee">{{ item.assignee }}</span>
          {{ state.text }}
        </span>

        <span v-else class="row__done">
          <wx-icon name="check" size="0.85em" />
          {{ state.text }}
        </span>
      </span>
    </template>

    <p class="row__preview">{{ item.preview }}</p>

    <template v-if="item.unread" #actions>
      <span class="row__dot" aria-label="Непрочитане" />
    </template>
  </wx-entity-card>
</template>

<style scoped>
.row {
  /*
   * A mail row is three lines that belong together, so the air around them has to
   * beat the air inside them — otherwise the preview of one row reads as the
   * beginning of the next. Hence the roomier padding against the tight inner gaps.
   */
  --wx-entity-card-padding: var(--wx-space-12) var(--wx-space-14);

  cursor: pointer;
  border-bottom: 1px solid var(--wx-border-default);
  border-radius: 0;
  /* Room for the accent bar a selected row grows on the left. */
  border-left: 3px solid transparent;
}

/*
 * A mail list marks the open row with a bar and a tint rather than an outline: the
 * rows touch, and a boxed one among them reads as a card that fell out of the list.
 */
.row.is-selected {
  border-color: var(--wx-border-default);
  border-left-color: var(--wx-color-primary);
}

/* Three lines of text: the avatar belongs at the top of them, not in the middle. */
.row :deep(.wx-entity-card__media) {
  align-self: flex-start;
  margin-top: 2px;
}

.row__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: var(--wx-radius-full);
  background: var(--wx-bg-fill);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
}

/* An unread row carries its colour in the avatar and its weight in the name. */
.row--unread .row__avatar {
  background: var(--wx-color-primary);
  color: var(--wx-color-primary-contrast);
}

.row__head {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-8);
}

.row__name {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row--unread .row__name {
  font-weight: var(--wx-font-weight-semibold);
}

.row--done .row__name {
  color: var(--wx-text-muted);
}

.row__time {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-regular);
}

.row__meta {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  /* The phone gives way before the row is allowed to widen the list. */
  overflow: hidden;
}

.row__muted {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.row__assignee {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
  font-size: 9px;
  font-weight: var(--wx-font-weight-semibold);
}

.row__done {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  color: var(--wx-color-success-active);
  font-size: var(--wx-font-size-xs);
}

.row__preview {
  margin: 2px 0 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-tight);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row--done .row__preview {
  color: var(--wx-text-placeholder);
}

.row__dot {
  display: block;
  width: 8px;
  height: 8px;
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary);
}
</style>
