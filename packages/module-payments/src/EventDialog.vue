<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  WxAlert,
  WxBadge,
  WxButton,
  WxDescriptions,
  WxDescriptionsItem,
  WxDialog,
  WxHeading,
  WxSegmented,
  WxSkeleton,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxDate } from '@webx-ui/module-admin'
import { createPaymentsApi } from './api'
import EventList from './EventList.vue'
import { codeTone, formattable, outcomeTone, pretty, size } from './format'
import { usePaymentsLines, usePaymentsWords } from './i18n'
import type { EventDetail, EventHalf, EventLine } from './types'

/**
 * One event of the exchange (spec §13.2 `payments.exchange.show`): what was decided, the request
 * and the answer with their headers and bodies — text in a monospace block, with indents only on
 * screen, or «Download» for a binary or large one — and the trace: the neighbours of the same
 * action. Without `payments.exchange` the server sends no headers and no bodies at all.
 */
const props = defineProps<{ id: number | null }>()
const open = defineModel<boolean>('open', { default: false })

const admin = useAdmin()
const api = createPaymentsApi(admin)
const t = usePaymentsWords()
const w = usePaymentsLines()
const message = useErrorText()

const current = ref<number | null>(null)
const event = ref<EventDetail | null>(null)
const loading = ref(false)
const failed = ref('')
const view = ref<'formatted' | 'raw'>('formatted')

async function load(id: number): Promise<void> {
  current.value = id
  loading.value = true
  failed.value = ''

  try {
    event.value = await api.event(id)
  } catch (error) {
    failed.value = message(error)
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.id, open.value] as const,
  ([id, shown]) => {
    if (shown && id !== null) void load(id)
  },
  { immediate: true },
)

const title = computed(() =>
  event.value ? w(`exchange.kinds.${event.value.kind}`) : t('exchange.title'),
)

const halves = computed(() => {
  const one = event.value

  if (!one?.bodies) return []

  return [
    { key: 'request' as const, label: t('exchange.request'), half: one.request },
    { key: 'response' as const, label: t('exchange.response'), half: one.response },
  ].filter((part) => part.half !== null) as {
    key: 'request' | 'response'
    label: string
    half: EventHalf
  }[]
})

const anyFormattable = computed(() => halves.value.some((part) => formattable(part.half.body)))

function headers(half: EventHalf): string {
  return Object.entries(half.headers)
    .map(([name, value]) => `${name}: ${Array.isArray(value) ? value.join(', ') : value}`)
    .join('\n')
}

function body(half: EventHalf): string {
  return view.value === 'formatted' && half.body !== null ? pretty(half.body) : (half.body ?? '')
}

function sizeOf(bytes: number | null): string {
  return size(bytes, t('exchange.bytes'), (kb) => t('exchange.kb', { size: kb }))
}

const masked = computed(() =>
  Object.entries(event.value?.masked ?? {})
    .filter(([, count]) => count > 0)
    .map(([name, count]) => `${name} × ${count}`)
    .join(', '),
)

function pick(line: EventLine): void {
  if (line.id !== current.value) void load(line.id)
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="880" scroll="body">
    <wx-skeleton v-if="loading && !event" :rows="6" />

    <wx-alert v-else-if="failed" type="danger" :description="failed" />

    <div v-else-if="event" class="wx-payments-event">
      <wx-descriptions :columns="2" size="sm" class="wx-payments-event__facts">
        <wx-descriptions-item :label="t('exchange.time')">
          <wx-date :value="event.at" />
        </wx-descriptions-item>
        <wx-descriptions-item :label="t('exchange.direction')">
          {{ w(`exchange.directions.${event.direction}`) }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.gateway" :label="t('exchange.gateway')">
          {{ event.gateway }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.method_key" :label="t('exchange.method')">
          {{ event.method_key }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.order_number" :label="t('attempts.order')">
          {{ event.order_number }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.payment_uuid" :label="t('exchange.payment')">
          <span class="wx-payments-event__mono">{{ event.payment_uuid }}</span>
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.source" :label="t('attempts.source')">
          {{ w(`statuses.sources.${event.source}`) }}
        </wx-descriptions-item>
        <wx-descriptions-item
          v-if="event.status !== null || event.transport_error"
          :label="t('exchange.code')"
        >
          <wx-badge :type="codeTone(event)" size="sm">
            {{ event.transport_error ? t('exchange.transport-error') : event.status }}
          </wx-badge>
          <wx-text v-if="event.transport_error" size="sm" tone="muted">
            {{ event.transport_error }}
          </wx-text>
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.signature" :label="t('exchange.signature')">
          {{ w(`exchange.signature.${event.signature}`) }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.outcome" :label="t('exchange.outcome')">
          <wx-badge :type="outcomeTone(event.outcome)" size="sm">
            {{ w(`exchange.outcomes.${event.outcome}`) }}
          </wx-badge>
          <wx-text v-if="event.message" size="sm" tone="muted">{{ event.message }}</wx-text>
        </wx-descriptions-item>
        <wx-descriptions-item
          v-if="event.status_before || event.status_after"
          :label="t('attempts.status')"
        >
          {{
            t('exchange.status-change', {
              before: event.status_before ? w(`statuses.statuses.${event.status_before}`) : '—',
              after: event.status_after ? w(`statuses.statuses.${event.status_after}`) : '—',
            })
          }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.reported" :label="t('exchange.reported')">
          {{ event.reported }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.duration_ms !== null" :label="t('exchange.duration')">
          {{ t('exchange.ms', { ms: event.duration_ms }) }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="event.ip" :label="t('exchange.ip')">
          {{ event.ip }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="masked" :label="w('exchange.masked')">
          {{ masked }}
        </wx-descriptions-item>
      </wx-descriptions>

      <div v-if="event.url" class="wx-payments-event__url">
        <wx-text size="sm" tone="muted">{{ t('exchange.url') }}</wx-text>
        <code class="wx-payments-event__mono">{{ event.http_method }} {{ event.url }}</code>
      </div>

      <wx-alert v-if="!event.bodies" type="info" :description="t('exchange.bodies-hidden')" />

      <template v-else>
        <wx-segmented
          v-if="anyFormattable"
          v-model="view"
          size="sm"
          :options="[
            { value: 'formatted', label: t('exchange.formatted') },
            { value: 'raw', label: t('exchange.raw') },
          ]"
          class="wx-payments-event__view"
        />

        <section
          v-for="part in halves"
          :key="part.key"
          class="wx-payments-event__half"
          :data-test="`half-${part.key}`"
        >
          <div class="wx-payments-event__head">
            <wx-heading :level="4">{{ part.label }}</wx-heading>
            <wx-text size="sm" tone="muted" class="wx-payments-event__size">
              <template v-if="part.half.size !== null">
                {{ w('exchange.size') }}: {{ sizeOf(part.half.size) }}
              </template>
              <wx-badge v-if="part.half.truncated" type="warning" size="sm">
                {{ w('exchange.truncated') }}
              </wx-badge>
            </wx-text>
            <wx-button
              v-if="part.half.has_body"
              size="sm"
              variant="outline"
              icon="download"
              :href="api.bodyUrl(event.id, part.key)"
              download
            >
              {{ w('exchange.download') }}
            </wx-button>
          </div>

          <template v-if="Object.keys(part.half.headers).length > 0">
            <wx-text size="sm" tone="muted">{{ t('exchange.headers') }}</wx-text>
            <pre class="wx-payments-event__pre">{{ headers(part.half) }}</pre>
          </template>

          <wx-text size="sm" tone="muted">{{ t('exchange.body') }}</wx-text>
          <pre
            v-if="part.half.body !== null && part.half.body !== ''"
            class="wx-payments-event__pre"
            >{{ body(part.half) }}</pre>
          <wx-text v-else-if="part.half.download" size="sm">{{
            t('exchange.binary-body')
          }}</wx-text>
          <wx-text v-else size="sm" tone="muted">{{ t('exchange.no-body') }}</wx-text>

          <wx-text v-if="part.half.sha256" size="sm" tone="muted" class="wx-payments-event__hash">
            {{ t('exchange.sha256') }}:
            <span class="wx-payments-event__mono">{{ part.half.sha256 }}</span>
          </wx-text>
        </section>
      </template>

      <section v-if="event.trace_events.length > 1" class="wx-payments-event__half">
        <wx-heading :level="4">{{ t('exchange.trace') }}</wx-heading>
        <event-list :events="event.trace_events" :current="current" @open="pick" />
      </section>
    </div>
  </wx-dialog>
</template>

<style scoped>
.wx-payments-event {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-payments-event__half {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-payments-event__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-payments-event__size {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-6);
  margin-inline-end: auto;
}

.wx-payments-event__url {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-payments-event__mono {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

/* A body is read as it came: long lines wrap inside the block, the page never scrolls sideways. */
.wx-payments-event__pre {
  max-height: 360px;
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  overflow: auto;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  line-height: 1.5;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.wx-payments-event__hash {
  overflow-wrap: anywhere;
}

.wx-payments-event__view {
  align-self: flex-start;
}
</style>
