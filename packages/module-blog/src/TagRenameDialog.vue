<script setup lang="ts">
import { nextTick, onMounted, ref, useId, useTemplateRef } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { useBlogMessages } from './i18n'

/**
 * Renaming a tag: one field, one button, and nothing changes until it is pressed.
 *
 * It used to be done in the cell — the name turned into a box when it was clicked. Two things
 * were wrong with that. A name that is quietly an `<input>` reads as a name: nothing says it can
 * be typed in, and a stray click on a row is a rename nobody asked for. And the box saved itself
 * on `blur`, which never fired: `blur` does not bubble, so the listener on the field's wrapper
 * heard nothing and clicking away simply lost what had been typed.
 *
 * The address deliberately does not follow the word. A tag respelled three times before lunch
 * would otherwise leave three aliases behind a decision nobody made; the address has a menu item
 * of its own.
 */
const props = defineProps<{ name: string }>()

const { open, resolve, dismiss } = useModal<string>()

useBlogMessages()

const t = useTranslate('webx-blog')
/* 'Save' is the panel's word, translated once for every module that has a form. */
const panel = useTranslate('webx-admin')

/* The footer button lives outside the form, so it says which form it submits. */
const formId = useId()

const draft = ref(props.name)
const field = useTemplateRef<HTMLFormElement>('field')

onMounted(async () => {
  await nextTick()

  const input = field.value?.querySelector('input')

  // Selected rather than merely focused: renaming is usually replacing, not appending.
  input?.focus()
  input?.select()
})

function submit(): void {
  const next = draft.value.trim()

  // An empty name is not a rename, it is a tag with nothing to call it by.
  if (next === '' || next === props.name) {
    dismiss()

    return
  }

  resolve(next)
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('tag.rename')" :width="420">
    <!-- A real form, so that Enter does what the button does. A listener on the field is not
         the same thing: a form with one text field submits on Enter by itself, which is what a
         one-field dialog is for. -->
    <form :id="formId" ref="field" @submit.prevent="submit">
      <wx-form-item :label="t('tag.field-title')" required>
        <!-- The key as well as the form. Implicit submission is a browser behaviour and this
             field lives inside a dialog that takes the keyboard over; measured here, a real
             Enter reached the input, was not prevented by anybody, and submitted nothing. -->
        <wx-input
          v-model="draft"
          :aria-label="t('tag.field-title')"
          @keydown.enter.prevent="submit"
        />
      </wx-form-item>
    </form>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('tag.cancel') }}</wx-button>
        <wx-button
          type="primary"
          native-type="submit"
          :form="formId"
          :disabled="draft.trim() === ''"
        >
          {{ panel('editor.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
