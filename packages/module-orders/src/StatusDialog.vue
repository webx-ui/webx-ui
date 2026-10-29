<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxBadge,
  WxButton,
  WxDialog,
  WxFormItem,
  WxRadio,
  WxSkeleton,
  WxSpace,
  WxText,
  WxTextarea,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { toneOf } from './tones'
import type { MassStatusAnswer, MoveConsequences, OrderDetail, PublicStatusGroup } from './types'

/**
 * Another internal status — for one order from its card, or for the selection of the list
 * (spec §11.2, §11.3). The choice is grouped by what the buyer sees; switched-off and deleted
 * statuses are not offered. Under it, before anything is done, the line of consequences from the
 * service's own dry run: whether the buyer sees a change and through which channels they hear of
 * it, which mark the status sets — or, for a batch, how many of each.
 */
const props = defineProps<{
  statuses: PublicStatusGroup[]
  /** The order of the card; without it, `ids` is the selection. */
  orderId?: number
  ids?: number[]
  current?: number | null
}>()

const { resolve, dismiss, open } = useModal<OrderDetail | MassStatusAnswer>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const chosen = ref<number | null>(null)
const note = ref('')
const single = ref<MoveConsequences | null>(null)
const batch = ref<MassStatusAnswer | null>(null)
const previewing = ref(false)
const applying = ref(false)
const error = ref<string | null>(null)

const mass = computed(() => props.orderId === undefined)
const count = computed(() => props.ids?.length ?? 0)

/** The groups with what may be chosen in them; a group with nothing to choose is not drawn. */
const groups = computed(() =>
  props.statuses
    .filter((group) => !group.deleted)
    .map((group) => ({
      ...group,
      statuses: group.statuses.filter((status) => status.active && !status.deleted),
    }))
    .filter((group) => group.statuses.length > 0),
)

let asked = 0

watch(chosen, async (id) => {
  single.value = null
  batch.value = null
  error.value = null

  if (id === null) return

  const mine = ++asked
  previewing.value = true

  try {
    if (mass.value) {
      const answer = await api.massStatus(props.ids ?? [], id, '', true)

      if (mine === asked) batch.value = answer
    } else {
      const answer = await api.previewStatus(props.orderId as number, id)

      if (mine === asked) single.value = answer
    }
  } catch (failure) {
    if (mine === asked) error.value = message(failure)
  } finally {
    if (mine === asked) previewing.value = false
  }
})

/** The single line of consequences, in the order a manager reads it. */
const lines = computed(() => {
  const move = single.value

  if (!move) return []
  if (!move.moved) return [t('panel.consequence-same')]

  const said = [
    move.public_changed
      ? t('panel.consequence-public', { status: move.public })
      : t('panel.consequence-quiet'),
  ]

  if (move.channels.length > 0) {
    said.push(t('panel.consequence-channels', { channels: move.channels.join(', ') }))
  }

  if (move.milestone)
    said.push(t('panel.consequence-milestone', { milestone: move.milestone.label }))

  return said
})

const refused = computed(() => single.value?.message ?? null)

const canApply = computed(() => {
  if (chosen.value === null || previewing.value || applying.value) return false
  if (mass.value) return (batch.value?.moved ?? 0) > 0

  return single.value !== null && single.value.moved && single.value.refusal === null
})

async function apply(): Promise<void> {
  if (!canApply.value || chosen.value === null) return

  applying.value = true

  try {
    resolve(
      mass.value
        ? await api.massStatus(props.ids ?? [], chosen.value, note.value, false)
        : await api.moveTo(props.orderId as number, chosen.value, note.value),
    )
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    applying.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="mass ? t('panel.mass-status-title', { count }) : t('panel.status-title')"
    :width="560"
  >
    <div class="wx-orders-status-dialog">
      <wx-form-item :label="t('panel.status')">
        <div class="wx-orders-status-dialog__groups" role="radiogroup">
          <div v-for="group in groups" :key="group.id" class="wx-orders-status-dialog__group">
            <wx-text size="sm" tone="muted" weight="medium">{{ group.name }}</wx-text>
            <div class="wx-orders-status-dialog__choices">
              <wx-radio
                v-for="status in group.statuses"
                :key="status.id"
                v-model="chosen"
                :value="status.id"
                :name="'wx-orders-status'"
                :disabled="applying"
              >
                <span class="wx-orders-status-dialog__choice">
                  <wx-badge :type="toneOf(status.color)" dot size="sm">{{ status.name }}</wx-badge>
                  <wx-text v-if="status.id === current" size="sm" tone="muted">
                    {{ t('panel.status-current') }}
                  </wx-text>
                </span>
              </wx-radio>
            </div>
          </div>
        </div>
      </wx-form-item>

      <div class="wx-orders-status-dialog__preview" aria-live="polite">
        <wx-skeleton v-if="previewing" :rows="2" />
        <wx-alert v-else-if="error" type="danger" :description="error" />
        <wx-alert v-else-if="refused" type="danger" :description="refused" />
        <wx-alert v-else-if="lines.length > 0" type="info">
          <p v-for="(line, index) in lines" :key="index" class="wx-orders-status-dialog__line">
            {{ line }}
          </p>
        </wx-alert>
        <wx-alert v-else-if="batch" type="info">
          <p class="wx-orders-status-dialog__line">
            {{ t('panel.mass-preview-moved', { count: batch.moved }) }}
          </p>
          <p v-if="batch.unchanged > 0" class="wx-orders-status-dialog__line">
            {{ t('panel.mass-preview-unchanged', { count: batch.unchanged }) }}
          </p>
          <p class="wx-orders-status-dialog__line">
            {{ t('panel.mass-preview-public', { count: batch.public_changed }) }}
          </p>
          <p class="wx-orders-status-dialog__line">
            {{ t('panel.mass-preview-notifications', { count: batch.notifications }) }}
          </p>
          <p v-if="batch.milestones > 0" class="wx-orders-status-dialog__line">
            {{ t('panel.mass-preview-milestones', { count: batch.milestones }) }}
          </p>
          <p v-if="batch.refused.length > 0" class="wx-orders-status-dialog__line">
            {{ t('panel.mass-preview-refused', { count: batch.refused.length }) }}
          </p>
        </wx-alert>
      </div>

      <wx-form-item :label="t('panel.note')" :help="t('panel.note-help')">
        <wx-textarea v-model="note" :rows="2" :maxlength="1000" :disabled="applying" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button type="primary" :loading="applying" :disabled="!canApply" @click="apply">
          {{ t('panel.apply') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-status-dialog {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-status-dialog__groups {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-status-dialog__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-orders-status-dialog__choices {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8) var(--wx-space-16);
}

.wx-orders-status-dialog__choice {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-orders-status-dialog__preview:empty {
  display: none;
}

.wx-orders-status-dialog__line {
  margin: 0;
}
</style>
