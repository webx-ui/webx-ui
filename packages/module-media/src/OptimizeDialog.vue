<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  useModal,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxProgress,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { createMediaApi } from './api'
import { readable, usePanelLocale } from './format'
import type { OptimizePending, OptimizeResult } from './types'

/**
 * «Optimize»: what is waiting, then a few pictures per request until they are done or stopped.
 *
 * The panel walks the list itself rather than leaving it to a queue: the count going up is the
 * only sign that something is happening to two hundred photographs, and «Stop» means the batch
 * in flight finishes and no other starts. However it closes, the manager draws the files again.
 */
const props = defineProps<{
  /** What the current settings have not been through: optimized in place, format kept. */
  plain: OptimizePending
  /** What «Convert to WebP» would take: the JPEG and PNG pictures, optimized or not. */
  convertible: OptimizePending
}>()

const { open, resolve } = useModal<true>()

const admin = useAdmin()
const api = createMediaApi(admin)
const t = useTranslate('webx-media')
const locale = usePanelLocale()
const message = useErrorText()

/** The server takes this many per request. */
const BATCH = 10

const state = ref<'ready' | 'running' | 'done'>('ready')
const done = ref(0)
const saved = ref(0)
const stopping = ref(false)
const failure = ref<string | null>(null)

/*
 * Off unless asked for, and asked for here rather than in the settings: converting changes the
 * address of every picture it touches and rewrites every page that names one. That is what the
 * owner wants once, deliberately — not what a click on «Optimize» should do by surprise.
 */
const convert = ref(false)
const counts = ref<Record<OptimizeResult['status'], number>>({
  optimized: 0,
  converted: 0,
  unchanged: 0,
  skipped: 0,
  missing: 0,
})
const references = ref(0)

const chosen = computed(() => (convert.value ? props.convertible : props.plain))
const total = computed(() => chosen.value.ids.length)

async function run(): Promise<void> {
  state.value = 'running'

  const ids = [...chosen.value.ids]
  const converting = convert.value

  try {
    for (let at = 0; at < ids.length && !stopping.value; at += BATCH) {
      const results = await api.optimize(ids.slice(at, at + BATCH), converting)

      for (const result of results) {
        saved.value += Math.max(0, result.before - result.after)
        counts.value[result.status] = (counts.value[result.status] ?? 0) + 1
        references.value += result.references ?? 0
      }

      done.value += results.length
    }
  } catch (error) {
    failure.value = message(error)
  }

  state.value = 'done'
}

function close(): void {
  resolve(true)
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="t('manager.optimize-title')"
    :width="480"
    :closable="state !== 'running'"
    :close-on-overlay="state !== 'running'"
    :close-on-escape="state !== 'running'"
  >
    <div class="wx-media-optimize">
      <template v-if="state === 'ready'">
        <wx-text>{{ t(convert ? 'manager.convert-text' : 'manager.optimize-text') }}</wx-text>

        <wx-checkbox v-model="convert" :disabled="convertible.ids.length === 0">
          {{ t('manager.convert') }}
        </wx-checkbox>

        <wx-text v-if="total === 0">{{ t('manager.optimize-none') }}</wx-text>
        <wx-text v-else weight="semibold">
          {{
            t(convert ? 'manager.convert-waiting' : 'manager.optimize-waiting', {
              count: total,
              size: readable(chosen.size, locale()),
            })
          }}
        </wx-text>
      </template>

      <template v-else>
        <wx-progress
          :value="done"
          :max="total"
          :status="failure ? 'danger' : state === 'done' ? 'success' : 'default'"
          show-value
          :formatter="(value: number, max: number) => `${value} / ${max}`"
          :aria-label="t('manager.optimize-title')"
        />
        <wx-text v-if="failure" size="sm" tone="danger">{{ failure }}</wx-text>
        <template v-if="state === 'done'">
          <wx-text>{{ t('manager.optimize-saved', { size: readable(saved, locale()) }) }}</wx-text>
          <wx-text v-if="convert" size="sm" tone="muted">
            {{
              t('manager.convert-report', {
                converted: counts.converted,
                unchanged: counts.unchanged + counts.skipped + counts.missing,
                references,
              })
            }}
          </wx-text>
        </template>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <template v-if="state === 'ready'">
          <wx-button variant="outline" @click="close()">{{ t('manager.cancel') }}</wx-button>
          <wx-button type="primary" :disabled="total === 0" @click="run">{{
            t(convert ? 'manager.convert-run' : 'manager.optimize')
          }}</wx-button>
        </template>
        <wx-button
          v-else-if="state === 'running'"
          variant="outline"
          :loading="stopping"
          @click="stopping = true"
          >{{ t('manager.optimize-stop') }}</wx-button
        >
        <wx-button v-else type="primary" @click="close()">{{
          t('manager.optimize-close')
        }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style>
.wx-media-optimize {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
