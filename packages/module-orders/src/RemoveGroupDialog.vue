<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxSelect,
  WxSkeleton,
  WxSpace,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { BoardGroup, StatusBoardData, StatusConsequences } from './types'

/**
 * A public status deleted (spec §5.6). It goes only empty: while stages still live in it, the
 * dialog lists them and offers to move them all to another group in the same action — the dry
 * run says whose buyers see a new name. Orders in its deleted stages keep showing its name.
 */
const props = defineProps<{ group: BoardGroup; groups: BoardGroup[] }>()

const { resolve, dismiss, open } = useModal<StatusBoardData>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const moveTo = ref<number | null>(null)
const answer = ref<StatusConsequences | null>(null)
const loading = ref(true)
const applying = ref(false)
const error = ref<string | null>(null)

const others = computed(() =>
  props.groups
    .filter((one) => one.id !== props.group.id && !one.deleted)
    .map((one) => ({ value: one.id, label: one.label })),
)

/** The stages that keep the group, from the first answer — they do not change with the choice. */
const alive = ref<{ id: number; name: string }[]>([])

let asked = 0

async function preview(): Promise<void> {
  const mine = ++asked
  loading.value = true
  error.value = null

  try {
    const got = await api.previewRemovePublic(props.group.id, moveTo.value)

    if (mine !== asked) return
    answer.value = got
    if (moveTo.value === null) alive.value = got.active
  } catch (failure) {
    if (mine === asked) error.value = message(failure)
  } finally {
    if (mine === asked) loading.value = false
  }
}

onMounted(() => void preview())
watch(moveTo, () => void preview())

const blocked = computed(
  () => answer.value?.refusal !== null && answer.value?.refusal !== undefined,
)

async function confirmed(): Promise<void> {
  applying.value = true

  try {
    resolve(await api.removePublic(props.group.id, moveTo.value))
  } catch (failure) {
    const errors = (failure as { errors?: Record<string, string[]> }).errors

    if (errors?.status?.[0]) error.value = errors.status[0]
    else toast.danger(message(failure))
  } finally {
    applying.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="t('panel.statuses.delete-public-title', { public: group.label })"
    :width="520"
  >
    <div class="wx-orders-remove-group" aria-live="polite">
      <template v-if="alive.length > 0">
        <p class="wx-orders-remove-group__line">{{ t('panel.statuses.delete-public-active') }}</p>
        <ul class="wx-orders-remove-group__stages">
          <li v-for="stage in alive" :key="stage.id">{{ stage.name }}</li>
        </ul>

        <wx-form-item
          :label="t('panel.statuses.move-to')"
          :help="t('panel.statuses.move-to-help')"
          required
        >
          <wx-select v-model="moveTo" :options="others" :disabled="applying" />
        </wx-form-item>
      </template>

      <wx-skeleton v-if="loading" :rows="2" />
      <wx-alert v-else-if="error" type="danger" :description="error" />
      <wx-alert v-else-if="blocked" type="warning" :description="answer?.message ?? ''" />
      <wx-alert v-else-if="answer && answer.lines.length > 0" type="info">
        <p v-for="(line, index) in answer.lines" :key="index" class="wx-orders-remove-group__line">
          {{ line }}
        </p>
      </wx-alert>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button
          type="danger"
          :loading="applying"
          :disabled="loading || blocked || error !== null"
          @click="confirmed"
        >
          {{ t('panel.statuses.delete') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-remove-group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-remove-group__line {
  margin: 0;
}

.wx-orders-remove-group__line + .wx-orders-remove-group__line {
  margin-top: var(--wx-space-4);
}

.wx-orders-remove-group__stages {
  margin: 0;
  padding-inline-start: var(--wx-space-18);
  color: var(--wx-text-muted);
}
</style>
