<script setup lang="ts">
import { ref } from 'vue'
import { WxUpload, useToast } from '@webx-ui/core'
import type { UploadFile } from '@webx-ui/core'

const toast = useToast()
const files = ref<UploadFile[]>([])

/** Stands in for the request. In an admin this is one `fetch` per file. */
function send(added: UploadFile[]) {
  for (const file of added) {
    file.status = 'uploading'
    const timer = setInterval(() => {
      file.progress = Math.min(100, file.progress + 12)
      if (file.progress < 100) return

      clearInterval(timer)
      file.status = file.name.includes('fail') ? 'error' : 'done'
      if (file.status === 'error') file.error = 'The server refused it'
    }, 160)
  }
}

function refuse(file: File, reason: string) {
  toast.warning(`${file.name}: ${reason === 'size' ? 'too large' : reason}`)
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-upload
      v-model="files"
      accept="image/*,.pdf"
      :max-size="2 * 1024 * 1024"
      :max="5"
      hint="Drop files here, or click to choose"
      @add="send"
      @reject="refuse"
    >
      <template #footer>Images or PDFs, up to 2 MB each, five at a time.</template>
    </wx-upload>

    <span class="wx-demo__note">
      Nothing is sent anywhere — the progress above is a timer. Name a file with “fail” in it to
      watch one go wrong.
    </span>
  </div>
</template>

<style scoped>
.wx-demo__note {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>
