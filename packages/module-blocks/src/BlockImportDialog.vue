<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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
import type { BlockImportDiff, BlockImportRow, BlockImportStatus } from './types'

/**
 * "Import" (§17.1): a file in, asked about twice. First the server reads it without writing —
 * what each type would become: new, unchanged, different from the file or refused, and why —
 * then, once the person has seen that, for real.
 *
 * A type that is already here is the site's (BLOCK_SCHEMAS §5): where it differs from the file
 * the row says how and is left alone, unless the person switches "Replace" on for that one row.
 * There is no switch for all of them — every replacement is a type someone looked at. A replaced
 * type gets a new draft version, so the site keeps drawing what it drew until someone publishes,
 * unless the switch to publish is on.
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
/** The text of the file, sent as it is: decoded here, its strings would be trimmed on the way. */
const file = ref<string | null>(null)
const rows = ref<BlockImportRow[] | null>(null)
/** Why the file was refused as a whole: not JSON, not a pack, a circle among its types. */
const refusal = ref<string | null>(null)
const publish = ref(false)
/** The slugs the person chose to take from the file, row by row. */
const replace = ref<string[]>([])
const reading = ref(false)
const importing = ref(false)
/** The rows are the outcome, not the plan: the import has run. */
const done = ref(false)

const STATUS: Record<BlockImportStatus, 'success' | 'primary' | 'default' | 'warning' | 'danger'> =
  {
    created: 'success',
    unchanged: 'default',
    differs: 'warning',
    replaced: 'primary',
    failed: 'danger',
  }

const writable = computed(() => (rows.value ?? []).filter((row) => row.status !== 'failed'))
const changes = computed(() =>
  writable.value.filter((row) => row.status === 'created' || row.status === 'replaced'),
)

/** Everything is already here as the file has it: the button would be a promise of nothing. */
const upToDate = computed(
  () =>
    rows.value !== null &&
    writable.value.length > 0 &&
    writable.value.every((row) => row.status === 'unchanged'),
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
  replace.value = []
  refusal.value = null
  done.value = false
  reading.value = true

  try {
    const text = await chosen.text()

    try {
      JSON.parse(text)
    } catch {
      file.value = null
      refusal.value = t('exchange.not-json')

      return
    }

    file.value = text
    await plan()
  } finally {
    reading.value = false
  }
}

/** The dry run — with the publish checks when publishing is asked, so the plan warns first. */
async function plan(): Promise<void> {
  if (file.value === null) return

  try {
    rows.value = await api.importPack(file.value, {
      name: name.value ?? undefined,
      dryRun: true,
      publish: publish.value,
      replace: replace.value,
    })
  } catch (error) {
    rows.value = null
    refusal.value = refusalOf(error)
  }
}

watch([publish, replace], () => {
  if (rows.value !== null && !done.value) void plan()
})

function replacing(slug: string): boolean {
  return replace.value.includes(slug)
}

function toggle(slug: string, on: boolean): void {
  replace.value = on ? [...replace.value, slug] : replace.value.filter((one) => one !== slug)
}

async function run(): Promise<void> {
  importing.value = true

  try {
    const result = await api.importPack(file.value ?? '', {
      name: name.value ?? undefined,
      publish: publish.value,
      replace: replace.value,
    })
    const written = result.filter((row) => row.status === 'created' || row.status === 'replaced')

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

/** The diff as label → line, the way the command prints it: `+` is the file's, `−` the site's. */
function diffLines(diff: BlockImportDiff): { label: string; text: string }[] {
  const lines: { label: string; text: string }[] = []

  if (diff.fields) {
    const parts = [
      ...diff.fields.added.map((key) => `+ ${key}`),
      ...diff.fields.removed.map((key) => `− ${key}`),
      ...diff.fields.changed.map((one) => `~ ${one.key} (${one.what})`),
    ]
    lines.push({ label: t('exchange.diff-fields'), text: parts.join('  ') })
  }

  for (const key of ['template', 'styles', 'script', 'sample'] as const) {
    const text = diff[key]
    if (text)
      lines.push({ label: t(`exchange.diff-${key}`), text: `+${text.added} −${text.removed}` })
  }

  if (diff.row) lines.push({ label: t('exchange.diff-row'), text: diff.row.join(', ') })

  return lines
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
                <details v-if="row.diff" class="wx-block-import__diff">
                  <summary>{{ t('exchange.replace-help') }}</summary>
                  <dl>
                    <template v-for="line in diffLines(row.diff)" :key="line.label">
                      <dt>{{ line.label }}</dt>
                      <dd>{{ line.text }}</dd>
                    </template>
                  </dl>
                </details>
                <span
                  v-if="row.error"
                  class="wx-block-import__error"
                  :class="{ 'is-warning': row.status !== 'failed' }"
                  >{{ row.error }}</span
                >
              </div>
              <div class="wx-block-import__state">
                <wx-badge :type="STATUS[row.status]" variant="soft" size="sm">
                  {{ statusLabel(row) }}
                </wx-badge>
                <wx-switch
                  v-if="row.diff && !done"
                  size="sm"
                  :model-value="replacing(row.slug)"
                  :label="t('exchange.replace')"
                  @update:model-value="toggle(row.slug, $event === true)"
                />
              </div>
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

.wx-block-import__state {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  align-items: flex-end;
  gap: var(--wx-space-4);
}

.wx-block-import__diff {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-block-import__diff summary {
  cursor: pointer;
}

.wx-block-import__diff dl {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: var(--wx-space-2) var(--wx-space-8);
  margin: var(--wx-space-4) 0 0;
}

.wx-block-import__diff dd {
  margin: 0;
  font-family: var(--wx-font-family-mono);
  overflow-wrap: anywhere;
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
