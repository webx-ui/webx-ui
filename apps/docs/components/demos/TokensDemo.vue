<script setup lang="ts">
import { ref } from 'vue'
import { WxInput, WxRichText, WxTextarea, type TokenOption } from '@webx-ui/core'

/* Example data: whatever a site keeps in its settings and prints in place of the brackets. */
const tokens: TokenOption[] = [
  { name: 'phone', value: '+1 555 0100', description: 'The main line' },
  { name: 'email', value: 'hello@example.com' },
  { name: 'year', value: '2026' },
]

const line = ref('Call us on [phone] — or [fax], which is not a placeholder')
const text = ref('Write to [email] any time.\nPrices are for [year]; [[year]] prints the brackets.')
const html = ref('<p>Questions? Call <strong>[phone]</strong> or type a bracket here: </p>')
const heading = ref('Open since [year]<span>.</span>')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Input — type <code>[</code>, or press the button</span>
      <wx-input v-model="line" :tokens="tokens" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">Value: {{ line }}</p>
    </div>

    <div>
      <span class="wx-demo__label">Textarea</span>
      <wx-textarea v-model="text" :tokens="tokens" autosize />
    </div>

    <div>
      <span class="wx-demo__label">Rich text</span>
      <wx-rich-text v-model="html" :tokens="tokens" min-height="80px" />
    </div>

    <div>
      <span class="wx-demo__label">Inline rich text</span>
      <wx-rich-text v-model="heading" :tokens="tokens" inline />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Value: {{ heading }}
      </p>
    </div>
  </div>
</template>
