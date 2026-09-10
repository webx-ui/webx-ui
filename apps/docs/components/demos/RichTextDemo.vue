<script setup lang="ts">
import { ref } from 'vue'
import { WxRichText } from '@webx-ui/core'

const html = ref(
  '<h2>Release notes</h2><p>Paste an image straight into this editor, or drop a file on it — ' +
    'the demo "uploads" it by turning it into a data URL.</p><ul><li>Lists</li><li>Tables</li>' +
    '<li>Links and YouTube embeds</li></ul>',
)

const log = ref<string[]>([])

/** Stands in for a real endpoint: resolves with a URL the editor then inserts. */
function upload(file: File) {
  log.value = [`upload(${file.name})`, ...log.value].slice(0, 4)
  return new Promise<{ url: string; alt: string }>((resolve) => {
    const reader = new FileReader()
    reader.onload = () =>
      setTimeout(() => resolve({ url: String(reader.result), alt: file.name }), 600)
    reader.readAsDataURL(file)
  })
}

/** Stands in for the media library that does not exist yet. */
async function pickImage() {
  log.value = ['pickImage()', ...log.value].slice(0, 4)
  return 'https://picsum.photos/seed/webx/640/360'
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-rich-text
      v-model="html"
      :upload="upload"
      :pick-image="pickImage"
      placeholder="Write something…"
      min-height="260px"
    />

    <details>
      <summary style="cursor: pointer; color: var(--wx-text-muted); font-size: 13px">
        HTML in the model
      </summary>
      <pre
        style="
          margin: 8px 0 0;
          padding: 12px;
          overflow-x: auto;
          background: var(--wx-bg-fill);
          border-radius: 8px;
          font-size: 12px;
        "
        >{{ html }}</pre>
    </details>

    <p v-if="log.length" style="margin: 0; color: var(--wx-text-muted); font-size: 13px">
      Calls: {{ log.join(' · ') }}
    </p>
  </div>
</template>
