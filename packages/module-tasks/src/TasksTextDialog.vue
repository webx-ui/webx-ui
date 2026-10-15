<script setup lang="ts">
import { nextTick, onMounted, ref, useId, useTemplateRef } from 'vue'
import { useModal, WxButton, WxDialog, WxFormItem, WxSpace, WxTextarea } from '@webx-ui/core'

/**
 * One text and a button: the remarks a stage is sent back with, the answer to an agent's
 * question, the reason a task is cancelled. Answers the text, or nothing when closed.
 *
 * `required` is for the two the rules insist on (§3): sending back without saying what is wrong,
 * or answering with nothing, would hand the next session a decision nobody made.
 */
defineOptions({ name: 'WxTasksTextDialog' })

const props = withDefaults(
  defineProps<{
    title: string
    label: string
    confirmText: string
    cancelText: string
    message?: string
    required?: boolean
    danger?: boolean
  }>(),
  { message: undefined, required: false, danger: false },
)

const { open, resolve, dismiss } = useModal<string>()

const formId = useId()
const text = ref('')
const form = useTemplateRef<HTMLFormElement>('form')

onMounted(async () => {
  await nextTick()
  form.value?.querySelector('textarea')?.focus()
})

function submit(): void {
  if (props.required && text.value.trim() === '') return

  resolve(text.value.trim())
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="props.title" :width="520">
    <p v-if="props.message" class="wx-tasks-text-dialog__message">{{ props.message }}</p>

    <form :id="formId" ref="form" @submit.prevent="submit">
      <wx-form-item :label="props.label" :required="props.required">
        <wx-textarea v-model="text" :rows="5" :aria-label="props.label" />
      </wx-form-item>
    </form>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ props.cancelText }}</wx-button>
        <wx-button
          :type="props.danger ? 'danger' : 'primary'"
          native-type="submit"
          :form="formId"
          :disabled="props.required && text.trim() === ''"
        >
          {{ props.confirmText }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-tasks-text-dialog__message {
  margin: 0 0 var(--wx-space-12);
  color: var(--wx-text-muted);
}
</style>
