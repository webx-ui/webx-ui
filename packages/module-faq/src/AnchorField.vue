<script setup lang="ts">
import { computed } from 'vue'
import { toast, WxButton, WxIcon, WxText } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useQuestionEditor } from './editor'
import { useFaqMessages } from './i18n'

/**
 * The anchor of a question: shown, never written (decision 10).
 *
 * Registered as a field only so that the screen draws it the way it draws a field — the label and
 * the help of its node around it. It has no name, so there is no value to bind, and it reads the
 * anchor off the question the form is editing.
 *
 * What is copied is the fragment, `#anchor`, and not an address: a question has no page of its
 * own, and the page it is linked on is whichever page the editor put the block on.
 */
defineOptions({ name: 'WxFaqAnchor' })

const editor = useQuestionEditor()
useFaqMessages()

const t = useTranslate('webx-faq')

const anchor = computed(() => editor?.question.value?.anchor ?? '')
const fragment = computed(() => (anchor.value === '' ? '' : `#${anchor.value}`))

async function copy(): Promise<void> {
  try {
    await navigator.clipboard.writeText(fragment.value)
    toast.success(t('question.link-copied'))
  } catch {
    // A browser that refuses the clipboard without a gesture it trusts; the anchor is on screen.
  }
}
</script>

<template>
  <div class="wx-faq-anchor">
    <template v-if="fragment !== ''">
      <wx-text class="wx-faq-anchor__value" mono truncate>{{ fragment }}</wx-text>
      <wx-button variant="outline" size="sm" @click="copy">
        <template #icon><wx-icon name="copy" /></template>
        {{ t('question.copy-link') }}
      </wx-button>
    </template>

    <wx-text v-else size="sm" tone="muted">{{ t('question.anchor-later') }}</wx-text>
  </div>
</template>

<style scoped>
.wx-faq-anchor {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-faq-anchor__value {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
