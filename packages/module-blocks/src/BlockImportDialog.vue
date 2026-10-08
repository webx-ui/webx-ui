<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxBadge,
  WxButton,
  WxDialog,
  WxEmpty,
  WxSkeleton,
  WxSpace,
  WxSwitch,
  WxText,
} from '@webx-ui/core'
import { createBlocksApi } from './api'
import { useBlocksMessages } from './i18n'
import type { BlockImportRow, BlockImportStatus } from './types'

/**
 * "Import" (§17.1): a file in, asked about twice. First the server reads it without writing —
 * what each type would become: new, updated, unchanged or refused, and why — then, once the
 * person has seen that, for real. A type that is already here is not replaced: it gets a new
 * draft version, so the site keeps drawing what it drew until someone publishes, unless the
 * switch to publish is on.
 *
 * Resolves with the rows written, so the section can fetch its list again; a run where some
 * type stayed behind keeps the dialog open on the result, because a toast would be gone before
 * the reason was read.
 */
const { resolve, dismiss, open } = useModal<BlockImportRow[]>()

const context = useAdmin()
const api = createBlocksApi(context)
useBlocksMessages()

const t = useTranslate('webx-blocks')
const message = useErrorText()

const picker = ref<HTMLInputElement | null>(null)
const name = ref<string | null>(null)
const file = ref<unknown>(null)
const rows = ref<BlockImportRow[] | null>(null)
/** Why the file was refused as a whole: not JSON, not a pack, a circle among its types. */
const refusal = ref<string | null>(null)
const publish = ref(false)
const reading = ref(false)
const importing = ref(false)
/** The rows are the outcome, not the plan: the import has run. */
const done = ref(false)

const STATUS: Record<BlockImportStatus, 'success' | 'primary' | 'default' | 'danger'> = {
  created: 'success',
  updated: 'primary',
  unchanged: 'default',
  failed: 'danger',
}

const writable = computed(() => (rows.value ?? []).filter((row) => row.status !== 'failed'))
const changes = computed(() =>
  writable.value.filter((row) => row.status !== 'unchanged' || (publish.value && row.writes)),
)

/** Nothing to write and nothing to publish: the button would be a promise of nothing. */
const upToDate = computed(
  () => rows.value !== null && writable.value.length > 0 && changes.value.length === 0,
)

function choose(): void {
  picker.value?.click()
}

function refusalOf(error: unknown): string {
  const body = (error as { status?: number; body?: { errors?: { file?: string[] } } }).body

  return body?.errors?.file?.[0] ?? message(error, t('exchange.import-failed'))
}

async function onFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const chosen = input.files?.[0]

  // The same file chosen again must still fire `change` — it is how a fixed file is re-read.
  input.value = ''

  if (!chosen) return

  name.value = chosen.name
  rows.value = null
  refusal.value = null
  done.value = false
  reading.value = true

  try {
    try {
      file.value = JSON.parse(await chosen.text())
    } catch {
      refusal.value = t('exchange.not-json')

      return
    }

    rows.value = await api.importPack(file.value, { name: chosen.name, dryRun: true })
  } catch (error) {
    refusal.value = refusalOf(error)
  } finally {
    reading.value = false
  }
}

async function run(): Promise<void> {
  importing.value = true

  try {
    const result = await api.importPack(file.value, {
      name: name.value ?? undefined,
      publish: publish.value,
    })
    const written = result.filter((row) => row.status !== 'failed' && row.status !== 'unchanged')

    toast.success(t('exchange.imported', { count: written.length }))

    if (result.some((row) => row.error !== null)) {
      rows.value = result
      done.value = true

      return
    }

    resolve(result)
  } catch (error) {
    refusal.value = refusalOf(error)
  } finally {
    importing.value = false
  }
}

/** Past the run, closing is the answer — with the rows, so the section knows to refresh. */
function close(): void {
  if (done.value && rows.value) resolve(rows.value)
  else dismiss()
}

function statusLabel(row: BlockImportRow): string {
  return t(`exchange.status-${row.status}`)
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('exchange.import-title')" :width="600">
    <div class="wx-block-import">
      <wx-text tone="muted" size="sm">{{ t('exchange.import-help') }}</wx-text>

      <input ref="picker" type="file" accept=".json,application/json" hidden @change="onFile" />

      <wx-empty
        v-if="name === null"
        icon="upload"
        :title="t('exchange.import-title')"
        class="wx-block-import__pick"
      >
        <template #actions>
          <wx-button type="primary" icon="upload" @click="choose">
            {{ t('exchange.choose-file') }}
          </wx-button>
        </template>
      </wx-empty>

      <template v-else>
        <div class="wx-block-import__file">
          <span class="wx-block-import__name">{{ name }}</span>
          <wx-button v-if="!done" size="sm" variant="text" @click="choose">
            {{ t('exchange.other-file') }}
          </wx-button>
        </div>

        <wx-skeleton v-if="reading" :rows="4" />

        <wx-alert v-else-if="refusal" type="danger" variant="soft" :description="refusal" />

        <template v-else-if="rows">
          <ul class="wx-block-import__list">
            <li v-for="row in rows" :key="row.slug" class="wx-block-import__row">
              <div class="wx-block-import__type">
                <span class="wx-block-import__title">{{ row.title ?? row.slug }}</span>
                <span class="wx-block-import__meta">
                  {{ row.slug }}
                  <template v-if="row.kind === 'component'">
                    · {{ t('components.kind-component') }}</template
                  >
                  <template v-if="done && row.version !== null"> · v{{ row.version }}</template>
                </span>
                <span
                  v-if="row.error"
                  class="wx-block-import__error"
                  :class="{ 'is-warning': row.status !== 'failed' }"
                  >{{ row.error }}</span
                >
              </div>
              <wx-badge :type="STATUS[row.status]" variant="soft" size="sm">
                {{ statusLabel(row) }}
              </wx-badge>
            </li>
          </ul>

          <wx-alert
            v-if="upToDate && !done"
            type="info"
            variant="soft"
            :description="t('exchange.up-to-date')"
          />

          <wx-switch
            v-if="!done && writable.length > 0"
            v-model="publish"
            :label="t('exchange.publish')"
          />
        </template>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="close">{{ t('page.cancel') }}</wx-button>
        <wx-button
          v-if="!done"
          type="primary"
          :loading="importing"
          :disabled="rows === null || changes.length === 0"
          @click="run"
        >
          {{ t('exchange.run') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-block-import {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-block-import__file {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-block-import__name {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-block-import__list {
  display: flex;
  flex-direction: column;
  max-height: 340px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

.wx-block-import__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--wx-space-12);
  padding: var(--wx-space-8) var(--wx-space-12);
}

.wx-block-import__row + .wx-block-import__row {
  border-top: 1px solid var(--wx-border-default);
}

.wx-block-import__type {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-block-import__meta {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-block-import__error {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-color-danger);
  overflow-wrap: anywhere;
}

.wx-block-import__error.is-warning {
  color: var(--wx-color-warning);
}
</style>
