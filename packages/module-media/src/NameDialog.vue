<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useTranslate } from '@webx-ui/admin'
import { useModal, WxButton, WxDialog, WxInput, WxSpace } from '@webx-ui/core'

/**
 * A name, asked for properly.
 *
 * `window.prompt` is not an option: a browser is free to refuse it — an embedded frame usually
 * does — and then the button simply does nothing, which is exactly how "new folder" looked.
 */
const props = withDefaults(
  defineProps<{
    title: string
    label?: string
    value?: string
    confirmText?: string
  }>(),
  { label: undefined, value: '', confirmText: undefined },
)

const { open, resolve, dismiss } = useModal<string>()

const t = useTranslate('webx-media')

const name = ref(props.value)
const input = useTemplateRef<HTMLElement>('input')

onMounted(async () => {
  await nextTick()
  input.value?.querySelector('input')?.focus()
})

function submit(): void {
  const value = name.value.trim()

  if (value !== '') {
    resolve(value)
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="420">
    <div ref="input">
      <!-- The dialog's title says what is being named, so the field carries the same words as a
           placeholder and as its accessible name rather than repeating them above itself. -->
      <wx-input
        v-model="name"
        :placeholder="label ?? t('manager.folder-name')"
        :aria-label="label ?? t('manager.folder-name')"
        @keyup.enter="submit"
      />
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('manager.cancel') }}</wx-button>
        <wx-button type="primary" :disabled="name.trim() === ''" @click="submit">
          {{ confirmText ?? t('manager.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
