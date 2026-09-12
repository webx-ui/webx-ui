<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { WxButton, WxImageEditor, openImageEditor, type ImageEditorResult } from '@webx-ui/core'

const photo = 'https://picsum.photos/seed/wx-editor/900/600'
const named = { src: photo, fileName: 'workspace.jpg' }

const preview = ref('')
const note = ref('Nothing yet')

/* The blob is only bytes; it takes a URL of its own to be looked at — and giving that
 * back is the caller's job, which is why the old one goes whenever a new one arrives. */
function show(result: ImageEditorResult | undefined, how: string) {
  if (!result) {
    note.value = `${how} — closed without saving`
    return
  }

  if (preview.value) URL.revokeObjectURL(preview.value)
  preview.value = URL.createObjectURL(result.blob)
  note.value = `${how} — ${result.file.name}, ${result.width}×${result.height}, ${Math.round(
    result.blob.size / 1024,
  )} KB, turned ${result.rotation}°`
}

onBeforeUnmount(() => {
  if (preview.value) URL.revokeObjectURL(preview.value)
})

async function edit() {
  show(await openImageEditor({ ...named }), 'From code')
}

async function avatar() {
  show(
    await openImageEditor({
      ...named,
      title: 'Crop the avatar',
      width: 560,
      aspect: 1,
      maxWidth: 256,
      format: 'image/jpeg',
      fileName: 'avatar',
    }),
    'Avatar',
  )
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">On the page — drag a box, take the corners, turn it</span>

      <wx-image-editor v-bind="named" @save="show($event, 'On the page')" />
    </div>

    <div>
      <span class="wx-demo__label">From code — the same editor in a panel</span>

      <div class="row">
        <wx-button @click="edit">Edit the picture</wx-button>
        <wx-button variant="outline" @click="avatar">Crop an avatar (1:1, 256px)</wx-button>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">What came out</span>

      <div class="result">
        <img v-if="preview" :src="preview" alt="The result" />
        <span v-else class="empty">Save something above</span>
      </div>

      <span class="wx-demo__note">{{ note }}</span>
    </div>
  </div>
</template>

<style scoped>
.row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.result {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 120px;
  padding: var(--wx-space-10);
  background: var(--wx-bg-body);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
}

.result img {
  max-width: 100%;
  max-height: 260px;
  border-radius: var(--wx-radius-sm);
}

.empty {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
