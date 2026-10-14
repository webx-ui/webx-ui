<script setup lang="ts">
import { computed, inject, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminKey, useErrorText, useTranslate, type AdminContext } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxSelect, WxSkeleton, WxText } from '@webx-ui/core'
import { createBlocksApi, createRegionsApi } from './api'
import { useBlocksMessages } from './i18n'
import { blocksFragmentKey } from './preview'
import type { RegionRow } from './types'

/**
 * `wx-fragment` — the one field of «Shared block» (§4.1 of the shared blocks spec): which shared
 * block of the site this place on the page shows.
 *
 * The field says what it has chosen as well as letting it be chosen, because the block's own
 * content is not here: is it on the site, where is it edited, and that an edit there reaches
 * every page it stands on. And it offers the way out — the blocks of the shared one copied into
 * this page in its place, to follow it no more.
 */
defineOptions({ name: 'WxFragmentField', inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    disabled?: boolean
    /** The shared blocks to choose from. Fetched from the panel when absent — a demo hands them in. */
    fragments?: RegionRow[] | null
  }>(),
  { modelValue: null, disabled: false, fragments: null },
)

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()

useBlocksMessages()
const t = useTranslate('webx-blocks')
const message = useErrorText()

/* `inject` and not `useAdmin()`: a field must draw outside a panel too, in a demo or a test. */
const admin: AdminContext | null = inject(adminKey, null)
const host = inject(blocksFragmentKey, null)
const router = useRouter()

const loaded = ref<RegionRow[] | null>(null)
const list = computed(() => props.fragments ?? loaded.value)
const copying = ref(false)

/* Not inside itself: the shared block being edited is not one of its own choices. */
const choices = computed(() =>
  (list.value ?? []).filter((row) => row.name !== (host?.own.value ?? null)),
)

const options = computed(() => choices.value.map((row) => ({ label: row.title, value: row.name })))

const chosen = computed(() => list.value?.find((row) => row.name === props.modelValue) ?? null)

type Status = 'live' | 'edits' | 'fallback' | 'empty'

/**
 * What the site shows in its place. Empty counts the tree being edited, so a published shared
 * block whose draft was emptied reads «with edits» first — that is the fact that will change.
 */
const status = computed<Status | null>(() => {
  const row = chosen.value

  if (!row) return null
  if (!row.published) return 'fallback'
  if (row.has_draft) return 'edits'

  return row.count === 0 ? 'empty' : 'live'
})

const badge = computed(() =>
  status.value === 'live' ? 'success' : status.value === 'edits' ? 'warning' : 'default',
)

/**
 * Where the shared block is edited: its own editor, in a new tab — the page being edited here
 * keeps its draft and its place. Only for whoever may go there, and only where the panel has it.
 */
const editHref = computed<string | null>(() => {
  const name = chosen.value?.name

  if (!name || !admin?.can('blocks.regions') || !router?.hasRoute('webx.regions.edit')) return null

  return router.resolve({ name: 'webx.regions.edit', params: { name } }).href
})

/** Only inside a constructor, which is what can put the copies in its place. */
const canDetach = computed(() => host !== null && admin !== null && chosen.value !== null)

function choose(value: unknown): void {
  emit('update:modelValue', typeof value === 'string' && value !== '' ? value : null)
}

/**
 * The shared block's published blocks, with keys of their own, in place of this one. Asked first,
 * because what it gives up is not on screen: the edits the shared block will get from now on.
 */
async function detach(): Promise<void> {
  const row = chosen.value
  const key = host?.selected.value

  if (!row || !key || !admin || !host) return

  const agreed = await confirm({
    title: t('fragment.detach-title', { title: row.title }),
    message: t('fragment.detach-text'),
    confirmText: t('fragment.detach'),
    cancelText: t('page.cancel'),
  })

  if (!agreed) return

  copying.value = true

  try {
    host.detach(key, await createRegionsApi(admin).copy(row.name))
    toast.success(t('fragment.detached', { title: row.title }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    copying.value = false
  }
}

onMounted(async () => {
  if (props.fragments !== null || !admin) return

  try {
    loaded.value = await createBlocksApi(admin).fragments()
  } catch (error) {
    loaded.value = []
    toast.danger(message(error))
  }
})
</script>

<template>
  <div class="wx-fragment-field">
    <wx-skeleton v-if="list === null" :rows="1" />

    <template v-else>
      <wx-select
        :model-value="modelValue ?? null"
        :options="options"
        :placeholder="t('fragment.choose')"
        :disabled="disabled"
        :aria-label="t('fragment.choose')"
        teleport
        @update:model-value="choose"
      />

      <wx-text v-if="choices.length === 0" size="sm" tone="muted">{{ t('fragment.none') }}</wx-text>

      <!-- A name the site no longer declares: the page prints nothing there, and says so here. -->
      <wx-text v-else-if="modelValue && !chosen" size="sm" tone="danger">{{
        t('fragment.missing', { name: modelValue })
      }}</wx-text>

      <div v-if="chosen" class="wx-fragment-field__about">
        <div class="wx-fragment-field__state">
          <wx-badge :type="badge" dot>{{ t(`fragment.status-${status}`) }}</wx-badge>
          <code>{{ chosen.name }}</code>
        </div>

        <wx-text size="sm" tone="muted">{{ t('fragment.explain') }}</wx-text>

        <div class="wx-fragment-field__actions">
          <wx-button
            v-if="editHref"
            size="sm"
            variant="outline"
            icon="external-link"
            :href="editHref"
            target="_blank"
          >
            {{ t('fragment.edit') }}
          </wx-button>
          <wx-button
            v-if="canDetach"
            size="sm"
            variant="outline"
            icon="copy"
            :loading="copying"
            :disabled="disabled || !chosen.published"
            @click="detach"
          >
            {{ t('fragment.detach') }}
          </wx-button>
        </div>

        <!-- Said beside the button rather than left for its disabled state to explain. -->
        <wx-text v-if="canDetach && !chosen.published" size="sm" tone="muted">{{
          t('fragment.unpublished')
        }}</wx-text>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-fragment-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-fragment-field__about {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-10) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

.wx-fragment-field__state {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.wx-fragment-field__state code {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-fragment-field__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}
</style>
