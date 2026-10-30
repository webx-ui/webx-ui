<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  WxSwitch,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type {
  BoardGroup,
  BoardStatus,
  MilestoneKey,
  StageInput,
  StatusBoardData,
  StatusColor,
  StatusConsequences,
} from './types'

/**
 * One stage of the shop written (spec §11.4): its name in every language, the public status its
 * buyers see, a colour from the palette — a tone, not a swatch, so the dark theme has its own — the
 * mark moving an order here sets, and whether it is offered at all.
 *
 * A stage moved to another public status changes what the buyers of its orders see at once: the
 * first «Save» asks the server what that does and says it; the second does it. The same choice a
 * drag makes, for a phone where dragging between columns is not what a thumb does.
 */
const props = defineProps<{
  status: BoardStatus | null
  groups: BoardGroup[]
  groupId?: number
  colors: { value: StatusColor; label: string }[]
  milestones: { value: MilestoneKey; label: string }[]
}>()

const { resolve, dismiss, open } = useModal<StatusBoardData>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const name = ref<LocalizedValue>({ ...(props.status?.name ?? {}) })
const group = ref<number | null>(
  props.status?.public_status_id ?? props.groupId ?? props.groups[0]?.id ?? null,
)
const color = ref<StatusColor>(props.status?.color ?? 'neutral')
const milestone = ref<MilestoneKey | ''>(props.status?.milestone ?? '')
const active = ref(props.status?.active ?? true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const preview = ref<StatusConsequences | null>(null)

const groupOptions = computed(() =>
  props.groups.filter((one) => !one.deleted).map((one) => ({ value: one.id, label: one.label })),
)
const milestoneOptions = computed(() => [
  { value: '', label: t('panel.statuses.milestone-none') },
  ...props.milestones.map((one) => ({ value: one.value, label: one.label })),
])

/** Moved to another group — what the buyers of its orders see changes. */
const rebinding = computed(
  () => props.status !== null && group.value !== props.status.public_status_id,
)

watch([group, active], () => {
  preview.value = null
  errors.value = {}
})

function input(): StageInput {
  return {
    public_status_id: group.value ?? 0,
    name: name.value as Record<string, string>,
    color: color.value,
    sets_milestone: milestone.value === '' ? null : milestone.value,
    is_active: active.value,
  }
}

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    // Said first, done on the second click.
    if (rebinding.value && preview.value === null && props.status) {
      preview.value = await api.previewStage(props.status.id, input())

      return
    }

    resolve(await api.saveStage(props.status?.id ?? null, input()))
  } catch (failure) {
    const found = (failure as { errors?: Record<string, string[]> }).errors

    if (found && Object.keys(found).length > 0) errors.value = found
    else toast.danger(message(failure))
  } finally {
    saving.value = false
  }
}

const nameError = computed(() => {
  const key = Object.keys(errors.value).find((one) => one === 'name' || one.startsWith('name.'))

  return key ? errors.value[key]?.[0] : undefined
})
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="status ? t('panel.statuses.edit-status') : t('panel.statuses.new-status')"
    :width="520"
  >
    <div class="wx-orders-stage-form">
      <wx-form-item :label="t('panel.statuses.name')" :error="nameError" required>
        <wx-input v-model="name" localized :maxlength="120" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.statuses.group')"
        :help="t('panel.statuses.group-help')"
        :error="errorOf('public_status_id')"
        required
      >
        <wx-select v-model="group" :options="groupOptions" />
      </wx-form-item>

      <wx-form-item :label="t('panel.statuses.color')" :error="errorOf('color')">
        <wx-select v-model="color" :options="colors" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.statuses.milestone')"
        :help="t('panel.statuses.milestone-help')"
        :error="errorOf('sets_milestone')"
      >
        <wx-select v-model="milestone" :options="milestoneOptions" />
      </wx-form-item>

      <wx-switch v-model="active" :label="t('panel.statuses.active')" />

      <div aria-live="polite" class="wx-orders-stage-form__said">
        <wx-alert v-if="errors.status" type="danger" :description="errors.status[0]" />
        <wx-alert v-else-if="preview?.refusal" type="danger" :description="preview.message ?? ''" />
        <wx-alert v-else-if="preview && preview.lines.length > 0" type="info">
          <p v-for="(line, index) in preview.lines" :key="index" class="wx-orders-stage-form__line">
            {{ line }}
          </p>
        </wx-alert>
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button
          type="primary"
          :loading="saving"
          :disabled="preview?.refusal != null"
          @click="save"
        >
          {{ preview && !preview.refusal ? t('panel.statuses.move') : t('panel.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-stage-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-stage-form__said:empty {
  display: none;
}

.wx-orders-stage-form__line {
  margin: 0;
}

.wx-orders-stage-form__line + .wx-orders-stage-form__line {
  margin-top: var(--wx-space-4);
}
</style>
