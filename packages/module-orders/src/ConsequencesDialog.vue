<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxSkeleton,
  WxSpace,
  type ButtonType,
} from '@webx-ui/core'
import { useErrorText, useTranslate } from '@webx-ui/module-admin'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { StatusBoardData, StatusConsequences } from './types'

/**
 * A change of the statuses said before it is made (spec §5.6): a stage deleted, or moved to another
 * public group by a drag. The lines are the server's dry run — how many orders stand there, what
 * their buyers will see, that nobody is told — and a refusal says why it cannot be done and what
 * to do instead; then only «Cancel» is left.
 */
const props = defineProps<{
  title: string
  confirmText: string
  tone?: ButtonType
  preview: () => Promise<StatusConsequences>
  apply: () => Promise<StatusBoardData>
}>()

const { resolve, dismiss, open } = useModal<StatusBoardData>()

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const answer = ref<StatusConsequences | null>(null)
const loading = ref(true)
const applying = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    answer.value = await props.preview()
  } catch (failure) {
    error.value = message(failure)
  } finally {
    loading.value = false
  }
})

async function confirmed(): Promise<void> {
  applying.value = true

  try {
    resolve(await props.apply())
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
  <wx-dialog v-model:open="open" :title="title" :width="520">
    <div class="wx-orders-consequences" aria-live="polite">
      <wx-skeleton v-if="loading" :rows="3" />
      <wx-alert v-else-if="error" type="danger" :description="error" />
      <wx-alert v-else-if="answer?.refusal" type="danger" :description="answer.message ?? ''" />
      <wx-alert v-else-if="answer && answer.lines.length > 0" type="info">
        <p v-for="(line, index) in answer.lines" :key="index" class="wx-orders-consequences__line">
          {{ line }}
        </p>
      </wx-alert>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button
          v-if="!answer?.refusal && !error"
          :type="tone ?? 'primary'"
          :loading="applying"
          :disabled="loading"
          @click="confirmed"
        >
          {{ confirmText }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-consequences {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-orders-consequences__line {
  margin: 0;
}

.wx-orders-consequences__line + .wx-orders-consequences__line {
  margin-top: var(--wx-space-4);
}
</style>
