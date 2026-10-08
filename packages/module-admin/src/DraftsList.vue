<script setup lang="ts">
import { ref, watch } from 'vue'
import { confirm, toast, WxBadge, WxButton, WxHeading, WxSkeleton, WxText } from '@webx-ui/core'
import { useAdmin } from './admin'
import DateText from './DateText.vue'
import { useErrorText } from './errors'
import type { DraftCopy } from './editing'
import { useTranslate } from './i18n'

/**
 * The copies of the draft between publications: what the history does not list.
 *
 * The autosave ring, and the drafts somebody else's save wrote over — an agent's edit under an
 * editor's «Keep mine», or the other way round. Put back with «Restore», which makes the copy the
 * draft again; nothing reaches the site until somebody publishes. Shared by every drafted
 * editor's history tab: the list is the same whatever the record is.
 */
defineOptions({ name: 'WxDrafts' })

const props = withDefaults(
  defineProps<{
    /** The record's name on the server's `editing/{entity}/{id}`. */
    entity: string
    id: string | number | null | undefined
    /** Whether this reader may put a copy back. */
    canRestore?: boolean
    /** Anything that changes when the record is saved or published: the list is read again. */
    stamp?: unknown
  }>(),
  { canRestore: false, stamp: undefined },
)

const emit = defineEmits<{
  /** A copy is the draft now: the editor reads the record again. */
  restored: []
}>()

const admin = useAdmin()
const t = useTranslate('webx-admin')
const message = useErrorText()

const drafts = ref<DraftCopy[] | null>(null)
const working = ref(false)

const base = (): string | null =>
  props.id === null || props.id === undefined || props.id === ''
    ? null
    : `${admin.apiPath}/editing/${props.entity}/${encodeURIComponent(String(props.id))}/drafts`

async function load(): Promise<void> {
  const url = base()

  if (url === null) return

  try {
    drafts.value = (await admin.http.get<{ data: DraftCopy[] }>(url)).data
  } catch {
    // A panel without the endpoint, or a record with no copies to speak of: say nothing.
    drafts.value = []
  }
}

async function restore(copy: DraftCopy): Promise<void> {
  const url = base()

  if (url === null) return

  const agreed = await confirm({
    title: t('editing.restore-title'),
    message: t('editing.restore-text'),
    confirmText: t('editing.restore'),
    cancelText: t('editing.cancel'),
  })

  if (!agreed) return

  working.value = true

  try {
    await admin.http.post(`${url}/${copy.id}/restore`)
    emit('restored')
    toast.success(t('editing.restored'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

watch(
  () => [props.entity, props.id, props.stamp],
  () => void load(),
  { immediate: true },
)
</script>

<template>
  <section v-if="drafts === null || drafts.length > 0" class="wx-drafts">
    <header class="wx-drafts__head">
      <wx-heading :level="3" size="sm">{{ t('editing.drafts') }}</wx-heading>
      <wx-text size="sm" tone="muted">{{ t('editing.drafts-text') }}</wx-text>
    </header>

    <div class="wx-drafts__list">
      <wx-skeleton v-if="drafts === null" class="wx-drafts__ghost" :rows="2" />
      <div v-for="copy in drafts ?? []" :key="copy.id" class="wx-drafts__row">
        <div class="wx-drafts__who">
          <date-text v-if="copy.created_at" :value="copy.created_at" size="md" tone="default" />
          <wx-text size="sm" tone="muted">
            {{ copy.author ?? t('editing.somebody') }} · {{ t(`editing.source-${copy.source}`) }}
          </wx-text>
        </div>
        <wx-badge :type="copy.kind === 'overwritten' ? 'warning' : 'default'" dot>
          {{ t(`editing.${copy.kind}`) }}
        </wx-badge>
        <wx-button
          v-if="canRestore"
          size="sm"
          variant="outline"
          :loading="working"
          @click="restore(copy)"
        >
          {{ t('editing.restore') }}
        </wx-button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.wx-drafts {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-drafts__head {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}

.wx-drafts__list {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-drafts__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-drafts__row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
}

.wx-drafts__row:last-child {
  border-block-end: 0;
}

.wx-drafts__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
