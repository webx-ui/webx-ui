<script setup lang="ts">
import { ref, useTemplateRef } from 'vue'
import WxButton from '../components/Button/Button.vue'
import WxDialog from '../components/Dialog/Dialog.vue'
import WxImageEditor from '../components/ImageEditor/ImageEditor.vue'
import { useModal } from '../composables/useModal'
import type { ImageEditorResult } from '../components/ImageEditor/types'

defineOptions({ name: 'WxImageEditorDialog', inheritAttrs: false })

/*
 * The editor in a panel — all `openImageEditor` is.
 *
 * It is here and not in the editor itself because an editor that always arrived in a
 * dialog could not be put on a page, and a page is where a big one belongs. The buttons
 * are the dialog's, so the editor is asked for `:footer="false"` and driven through its
 * exposed `apply()`.
 *
 * Everything meant for the editor rides through on `$attrs` and is deliberately not
 * declared here. A wrapper that re-declares the props it forwards turns every boolean the
 * caller left alone into `false` — Vue casts an absent Boolean prop rather than leaving it
 * undefined — and the child's own `true` defaults are lost on the way through. That is how
 * the turns and the mirrorings went missing from this panel.
 */
withDefaults(
  defineProps<{
    /* Declared because the editor requires it; every other prop rides on $attrs. */
    src: string | Blob
    title?: string
    width?: number | string
    /* The dialog's own buttons, so they are named here and not passed on. */
    saveLabel?: string
    cancelLabel?: string
  }>(),
  {
    title: 'Edit picture',
    width: 760,
    saveLabel: 'Save',
    cancelLabel: 'Cancel',
  },
)

defineEmits<{ save: [result: ImageEditorResult] }>()

const { open, dismiss } = useModal<ImageEditorResult>()

const editor = useTemplateRef<{ apply: () => Promise<ImageEditorResult | undefined> }>('editor')
const busy = ref(false)

async function save() {
  busy.value = true
  try {
    await editor.value?.apply()
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="width" :close-on-overlay="false">
    <wx-image-editor
      ref="editor"
      v-bind="$attrs"
      :src="src"
      :footer="false"
      @save="$emit('save', $event)"
    />

    <template #footer>
      <wx-button variant="outline" :disabled="busy" @click="dismiss()">{{ cancelLabel }}</wx-button>
      <wx-button type="primary" :loading="busy" @click="save">{{ saveLabel }}</wx-button>
    </template>
  </wx-dialog>
</template>
