<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { toast, WxBadge, WxButton, WxCard, WxEmpty, WxSkeleton, WxSwitch } from '@webx-ui/core'
import { useAdmin, useErrorText, WxDate, WxListScreen } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import CarrierDialog from './CarrierDialog.vue'
import { useDeliveryWords } from './i18n'
import type { CarrierDirectory, CarrierRow } from './types'

/**
 * The carriers (§14.2): each on or off, and each of its directories — localities, points,
 * streets — with its last success, a run going on, a pass held at the threshold, whether it is
 * stale, how many records it has, and the remarks of its last pass. «Run» sends a directory to
 * the queue; «Confirm removal» lets a held pass remove what the carrier no longer has. The
 * settings and keys of a carrier, and its runs, open in a dialog.
 */
const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const rows = ref<CarrierRow[]>([])
const loading = ref(true)
const busy = ref<string | null>(null)
const editing = ref<string | null>(null)
const editorOpen = ref(false)

const canManage = computed(() => context.can('delivery.manage'))
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'delivery-carriers')?.title ??
    t('sections.carriers'),
)

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = await api.carriers()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

async function toggle(row: CarrierRow, on: boolean): Promise<void> {
  try {
    await api.saveCarrier(row.key, { enabled: on })
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function run(row: CarrierRow, directory: CarrierDirectory): Promise<void> {
  busy.value = `${row.key}:${directory.directory}`

  try {
    const { queued } = await api.runSync(row.key, directory.directory)

    toast.success(
      t('carrier.queued', {
        list: queued.map((one) => t(`carrier.directories.${one}`)).join(', '),
      }),
    )
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    busy.value = null
  }
}

async function confirmRemoval(row: CarrierRow, directory: CarrierDirectory): Promise<void> {
  busy.value = `${row.key}:${directory.directory}`

  try {
    await api.confirmRemoval(row.key, directory.directory)
    toast.success(t('carrier.confirmed'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    busy.value = null
  }
}

function remark(item: { key: string; count: number }): string {
  return t(`carrier.remarks.${item.key.replace('.', '-')}`, { count: item.count })
}

function open(key: string): void {
  editing.value = key
  editorOpen.value = true
}
</script>

<template>
  <wx-list-screen :title="title" :card="false">
    <wx-skeleton v-if="loading && rows.length === 0" :rows="4" />

    <wx-card v-else-if="rows.length === 0">
      <wx-empty :title="t('carrier.empty')" :description="t('carrier.empty-help')" />
    </wx-card>

    <div v-else class="wx-delivery-carriers">
      <wx-card v-for="row in rows" :key="row.key" :title="row.title" class="wx-delivery-carrier">
        <template #extra>
          <span class="wx-delivery-carrier__head">
            <wx-switch
              :model-value="row.enabled"
              :disabled="!canManage"
              :aria-label="t('carrier.enabled')"
              @update:model-value="toggle(row, Boolean($event))"
            />
            <wx-button size="sm" variant="outline" @click="open(row.key)">
              {{ t('carrier.settings') }}
            </wx-button>
          </span>
        </template>

        <p
          v-if="row.missing && row.missing.length > 0"
          class="wx-delivery-carrier__missing"
          data-test="carrier-missing"
        >
          {{ t('options.missing-list', { list: row.missing.join(', ') }) }}
        </p>

        <p v-if="row.queue > 0" class="wx-delivery-carrier__queue">
          {{ t('carrier.queue') }}: <strong>{{ row.queue }}</strong>
          <router-link :to="{ name: 'webx.delivery.bindings', query: { carrier: row.key } }"
            >→</router-link
          >
        </p>

        <div v-for="dir in row.directories" :key="dir.directory" class="wx-delivery-dir">
          <div class="wx-delivery-dir__main">
            <span class="wx-delivery-dir__name">{{
              t(`carrier.directories.${dir.directory}`)
            }}</span>
            <span class="wx-delivery-dir__facts">
              {{ t('carrier.records') }}: {{ dir.records }} · {{ t('carrier.schedule') }}:
              <code>{{ dir.cron ?? t('carrier.manual') }}</code>
            </span>
            <span class="wx-delivery-dir__facts">
              {{ t('carrier.last-success') }}:
              <wx-date v-if="dir.last_success" :value="dir.last_success" />
              <template v-else>{{ t('carrier.never') }}</template>
            </span>
            <span class="wx-delivery-dir__badges">
              <wx-badge v-if="dir.running" type="primary" dot>{{ t('carrier.running') }}</wx-badge>
              <wx-badge v-if="dir.held !== null" type="warning" dot>
                {{ t('carrier.held', { count: dir.held }) }}
              </wx-badge>
              <wx-badge v-if="dir.stale" type="danger" dot>{{ t('carrier.stale') }}</wx-badge>
              <wx-badge v-if="dir.latest?.status === 'failed'" type="danger" size="sm">
                {{ t('carrier.statuses.failed') }}
              </wx-badge>
            </span>
            <span v-for="item in dir.remarks" :key="item.key" class="wx-delivery-dir__remark">
              {{ remark(item) }}
            </span>
          </div>

          <span v-if="canManage" class="wx-delivery-dir__actions">
            <wx-button
              v-if="dir.held !== null"
              size="sm"
              type="warning"
              :loading="busy === `${row.key}:${dir.directory}`"
              @click="confirmRemoval(row, dir)"
            >
              {{ t('carrier.confirm') }}
            </wx-button>
            <wx-button
              size="sm"
              icon="refresh"
              :disabled="dir.running || !row.enabled"
              :loading="busy === `${row.key}:${dir.directory}`"
              :data-test="`run-${row.key}-${dir.directory}`"
              @click="run(row, dir)"
            >
              {{ t('carrier.run') }}
            </wx-button>
          </span>
        </div>
      </wx-card>
    </div>

    <carrier-dialog v-model:open="editorOpen" :carrier="editing" @saved="load" />
  </wx-list-screen>
</template>

<style scoped>
.wx-delivery-carriers {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-delivery-carrier__head {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-delivery-carrier__queue {
  margin: 0 0 var(--wx-space-12);
}

.wx-delivery-carrier__missing {
  margin: 0 0 var(--wx-space-12);
  color: var(--wx-color-warning);
}

.wx-delivery-dir {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8) var(--wx-space-16);
  padding: var(--wx-space-12) 0;
  border-top: 1px solid var(--wx-border-default);
}

.wx-delivery-dir__main {
  display: flex;
  flex: 1 1 280px;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-delivery-dir__name {
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-dir__facts,
.wx-delivery-dir__remark {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-dir__remark {
  color: var(--wx-color-warning);
}

.wx-delivery-dir__badges {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}

.wx-delivery-dir__badges:empty {
  display: none;
}

.wx-delivery-dir__actions {
  display: inline-flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}
</style>
