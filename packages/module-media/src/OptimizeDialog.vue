<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxProgress, WxSpace, WxText } from '@webx-ui/core'
import { createMediaApi } from './api'
import { readable } from './format'

/**
 * «Optimize»: what is waiting, then a few pictures per request until they are done or stopped.
 *
 * The panel walks the list itself rather than leaving it to a queue: the count going up is the
 * only sign that something is happening to two hundred photographs, and «Stop» means the batch
 * in flight finishes and no other starts. However it closes, the manager draws the files again.
 */
const props = defineProps<{
  ids: number[]
  /** What they weigh now, for the question asked before anything runs. */
  size: number
}>()

const { open, resolve } = useModal<true>()

const admin = useAdmin()
const api = createMediaApi(admin)
const t = useTranslate('webx-media')
const message = useErrorText()

/** The server takes this many per request. */
const BATCH = 10

const state = ref<'ready' | 'running' | 'done'>('ready')
const done = ref(0)
const saved = ref(0)
const stopping = ref(false)
const failure = ref<string | null>(null)

const total = computed(() => props.ids.length)

async function run(): Promise<void> {
  state.value = 'running'

  try {
    for (let at = 0; at < props.ids.length && !stopping.value; at += BATCH) {
      const results = await api.optimize(props.ids.slice(at, at + BATCH))

      for (const result of results) {
        saved.value += Math.max(0, result.before - result.after)
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
        <wx-text>{{ t('manager.optimize-text') }}</wx-text>
        <wx-text weight="semibold">
          {{ t('manager.optimize-waiting', { count: total, size: readable(size) }) }}
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
        <wx-text v-else-if="state === 'done'">
          {{ t('manager.optimize-saved', { size: readable(saved) }) }}
        </wx-text>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <template v-if="state === 'ready'">
          <wx-button variant="outline" @click="close()">{{ t('manager.cancel') }}</wx-button>
          <wx-button type="primary" @click="run">{{ t('manager.optimize') }}</wx-button>
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
