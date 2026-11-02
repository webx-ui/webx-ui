<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxEmpty,
  WxPopconfirm,
  WxSkeleton,
  WxSortableList,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxListScreen } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import LocalitySearch from './LocalitySearch.vue'
import type { LocalityBrief } from './types'

/**
 * The popular towns of the checkout's dialog (decision 40): one list for the site, the buttons
 * under the search in the order dragged here. «Add» searches the shared geography; a locality
 * switched off stays in the list, marked — the dialog skips it until it is on again.
 */
const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const rows = ref<LocalityBrief[]>([])
const loading = ref(true)
const canManage = computed(() => context.can('delivery.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'delivery-popular')?.title ??
    t('sections.popular'),
)

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = await api.popular()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

async function add(locality: LocalityBrief): Promise<void> {
  if (rows.value.some((row) => row.id === locality.id)) {
    toast.info(t('popular.already'))

    return
  }

  try {
    rows.value = await api.addPopular(locality.id)
    toast.success(t('popular.added'))
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(row: LocalityBrief): Promise<void> {
  try {
    rows.value = await api.removePopular(row.id)
    toast.success(t('popular.removed'))
  } catch (error) {
    toast.danger(message(error))
  }
}

async function reorder(): Promise<void> {
  try {
    rows.value = await api.setPopular(rows.value.map((row) => row.id))
  } catch (error) {
    toast.danger(message(error, t('common.order-failed')))
    await load()
  }
}
</script>

<template>
  <wx-list-screen :title="title" :card="false">
    <wx-card class="wx-delivery-popular">
      <div v-if="canManage" class="wx-delivery-popular__add">
        <locality-search
          creatable
          :exclude="rows.map((row) => row.id)"
          :placeholder="t('popular.add')"
          @pick="add"
        />
        <span class="wx-delivery-popular__help">{{ t('popular.add-help') }}</span>
      </div>

      <wx-skeleton v-if="loading && rows.length === 0" :rows="4" />

      <wx-empty
        v-else-if="rows.length === 0"
        :title="t('popular.empty')"
        :description="t('popular.empty-help')"
      />

      <template v-else>
        <wx-sortable-list
          v-model="rows"
          class="wx-delivery-popular__list"
          hoverable
          plain
          item-key="id"
          :handle="canManage ? 'grip' : 'row'"
          :item-label="(item: LocalityBrief) => item.name"
          :disabled="!canManage"
          data-test="popular-list"
          @move="reorder"
        >
          <template #default="{ item }">
            <span class="wx-delivery-popular__row">
              <span class="wx-delivery-popular__name">{{ item.name }}</span>
              <span class="wx-delivery-popular__label">{{ item.label }}</span>
              <wx-badge v-if="!item.active" size="sm" type="warning" dot>
                {{ t('popular.hidden') }}
              </wx-badge>
            </span>
          </template>

          <!-- Asked first, with the bin every dragged list of the panel removes by (SEO → links). -->
          <template v-if="canManage" #actions="{ item }">
            <wx-popconfirm
              :title="t('popular.remove-title', { name: item.name })"
              :confirm-text="t('popular.remove')"
              :cancel-text="t('common.cancel')"
              confirm-type="danger"
              align="end"
              @confirm="remove(item)"
            >
              <template #trigger>
                <wx-button
                  size="sm"
                  variant="text"
                  icon="trash"
                  :aria-label="t('popular.remove')"
                  data-test="popular-remove"
                />
              </template>
            </wx-popconfirm>
          </template>
        </wx-sortable-list>

        <wx-alert
          v-if="canManage && rows.length > 1"
          class="wx-delivery-popular__note"
          type="info"
          :description="t('common.drag')"
        />
      </template>
    </wx-card>
  </wx-list-screen>
</template>

<style scoped>
.wx-delivery-popular__add {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  max-width: 480px;
  margin-bottom: var(--wx-space-16);
}

.wx-delivery-popular__help {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

/* Off the card's edge on both sides: the bin at the end gets room, as the grip at the start has. */
.wx-delivery-popular__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-8);
  padding-inline: var(--wx-space-4) var(--wx-space-8);
}

.wx-delivery-popular__row {
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
  padding-block: var(--wx-space-8);
}

.wx-delivery-popular__name {
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-popular__label {
  min-width: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-popular__note {
  margin-top: var(--wx-space-12);
}
</style>
