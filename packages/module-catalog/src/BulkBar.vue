<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  createModal,
  toast,
  WxButton,
  WxDropdown,
  WxDropdownItem,
  WxIcon,
  WxProgress,
  WxText,
} from '@webx-ui/core'
import BulkParamsDialog from './BulkParamsDialog.vue'
import ExchangeExportDialog from './ExchangeExportDialog.vue'
import { createCatalogApi } from './api'
import { useCatalogMessages } from './i18n'
import type { ExchangeRun } from './exchange'
import type { BulkActionInfo, BulkRun, BulkSelection } from './types'

/**
 * The actions over what is picked in the list (§11.4), and the run they start.
 *
 * Which actions there are is the server's answer — the core's and every satellite's, only the
 * ones this administrator may start. A small selection is done inside the request and answered at
 * once; a large one is a run in the background, polled here with its progress, and its refusals
 * are listed by product when it ends. The list reloads either way, and `finished` carries the ids
 * that refused — what the list leaves picked, so they can be put right and tried again.
 *
 * «Export» heads the menu (§8.1 of the exchange spec): the same selection, written to a file. It
 * is the exchange's run rather than a bulk one, and is followed in «Exchange».
 */
const props = withDefaults(
  defineProps<{ count: number; selection: BulkSelection; base?: string }>(),
  { base: '/catalog' },
)
const emit = defineEmits<{ finished: [refused: number[]] }>()

/** Often enough to look alive, rarely enough that a run of forty thousand is not a request storm. */
const POLL = 1500

const context = useAdmin()
const api = createCatalogApi(context)
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const params = createModal<Record<string, unknown>, { action: BulkActionInfo; count: number }>(
  BulkParamsDialog,
)
const exporter = createModal<ExchangeRun, { selection: BulkSelection; count: number }>(
  ExchangeExportDialog,
)
const router = useRouter()

async function exportSelected(): Promise<void> {
  const started = await exporter({ selection: props.selection, count: props.count })

  if (!started) return

  toast.success(t('panel.exchange-export-started'))
  void router.push({ path: `${props.base}/exchange`, query: { run: String(started.id) } })
}

const actions = ref<BulkActionInfo[]>([])
const run = ref<BulkRun | null>(null)
const starting = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

const running = computed(
  () => run.value !== null && (run.value.status === 'queued' || run.value.status === 'running'),
)
const refused = computed(() =>
  run.value !== null && !running.value && run.value.failed > 0 ? run.value : null,
)

onMounted(async () => {
  try {
    actions.value = await api.bulkActions()
  } catch {
    actions.value = []
  }
})

onBeforeUnmount(() => clearTimeout(timer))

/** An action that asks for something, or deletes, is said once more with the number in it. */
function asks(action: BulkActionInfo): boolean {
  return action.params.length > 0 || (action.permission === 'catalog.delete' && !action.trashed)
}

async function start(action: BulkActionInfo): Promise<void> {
  let given: Record<string, unknown> = {}

  if (asks(action)) {
    const answer = await params({ action, count: props.count })

    if (!answer) return
    given = answer
  }

  starting.value = true

  try {
    follow(await api.startBulk(action.key, given, props.selection))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    starting.value = false
  }
}

function follow(answer: BulkRun): void {
  run.value = answer

  if (answer.status === 'queued' || answer.status === 'running') {
    timer = setTimeout(() => void poll(answer.id!), POLL)

    return
  }

  finish(answer)
}

async function poll(id: number): Promise<void> {
  try {
    follow(await api.bulkRun(id))
  } catch {
    // A missed answer is not the end of the run: ask again.
    timer = setTimeout(() => void poll(id), POLL * 2)
  }
}

function finish(answer: BulkRun): void {
  if (answer.total === 0) toast.warning(t('panel.bulk-nothing'))
  else if (answer.status === 'failed') toast.danger(t('panel.bulk-stopped', { count: answer.done }))
  else if (answer.failed === 0) toast.success(t('panel.bulk-done', { count: answer.done }))

  if (answer.failed === 0 && answer.status !== 'failed') run.value = null

  emit(
    'finished',
    answer.errors.map((error) => error.id),
  )
}
</script>

<template>
  <div class="wx-catalog-bulk">
    <wx-dropdown v-if="!running" align="start">
      <template #trigger>
        <wx-button size="sm" variant="outline" :loading="starting">
          {{ t('panel.bulk-actions') }}
          <wx-icon name="chevron-down" size="sm" />
        </wx-button>
      </template>

      <wx-dropdown-item @click="exportSelected">
        {{ t('panel.exchange-export') }}
      </wx-dropdown-item>
      <wx-dropdown-item
        v-for="action in actions"
        :key="action.key"
        :tone="action.permission === 'catalog.delete' && !action.trashed ? 'danger' : 'default'"
        @click="start(action)"
      >
        {{ action.label }}
      </wx-dropdown-item>
    </wx-dropdown>

    <div v-if="running && run" class="wx-catalog-bulk__progress" role="status">
      <wx-progress
        :value="run.done + run.failed"
        :max="Math.max(1, run.total)"
        size="sm"
        :aria-label="run.label"
      />
      <wx-text size="sm" tone="muted">
        {{ run.label }} ·
        {{ t('panel.bulk-running', { done: run.done + run.failed, total: run.total }) }}
      </wx-text>
    </div>

    <div v-if="refused" class="wx-catalog-bulk__refused">
      <wx-text size="sm" weight="medium">
        {{ refused.label }} · {{ t('panel.bulk-done', { count: refused.done }) }} ·
        {{ t('panel.bulk-refused', { count: refused.failed }) }}
      </wx-text>
      <details class="wx-catalog-bulk__errors">
        <summary>{{ t('panel.bulk-errors') }}</summary>
        <ul>
          <li v-for="error in refused.errors" :key="error.id">
            <strong>{{ error.name }}</strong> — {{ error.message }}
          </li>
        </ul>
      </details>
      <wx-button size="sm" variant="text" @click="run = null">{{
        t('panel.bulk-close')
      }}</wx-button>
    </div>
  </div>
</template>

<style scoped>
/* No box of its own: the button, the progress and the refusals stand in the selection strip
   around it, which already wraps — a box here would push the button onto a line of its own. */
.wx-catalog-bulk {
  display: contents;
}

.wx-catalog-bulk__progress {
  display: grid;
  gap: var(--wx-space-4);
  flex: 1 1 240px;
  min-width: 0;
}

.wx-catalog-bulk__refused {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-8);
  flex-basis: 100%;
  min-width: 0;
}

.wx-catalog-bulk__errors {
  flex-basis: 100%;
  font-size: var(--wx-font-size-sm);
}

.wx-catalog-bulk__errors summary {
  cursor: pointer;
  color: var(--wx-text-muted);
}

.wx-catalog-bulk__errors ul {
  margin: var(--wx-space-8) 0 0;
  padding-inline-start: var(--wx-space-18);
  max-height: 200px;
  overflow: auto;
}
</style>
