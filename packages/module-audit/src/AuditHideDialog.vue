<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxButton, WxDialog, WxFormItem, WxInput, WxText, WxTextarea, toast } from '@webx-ui/core'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditIgnoreRule, AuditIssue } from './types'

/**
 * «Hide» on a finding (decision 9): which addresses — this one, a mask of them, or the whole
 * check — and why. While the mask is typed the dialog says how many findings of the last run it
 * would hide; nothing is hidden until the button is pressed, and nothing is ever deleted.
 */
const props = defineProps<{ issue: AuditIssue; title: string }>()
const emit = defineEmits<{ hidden: [rule: AuditIgnoreRule] }>()
const open = defineModel<boolean>('open', { default: false })

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()

const pattern = ref('')
const reason = ref('')
const count = ref<number | null>(null)
const saving = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

/* The path, not the whole address: a mask of the path hides it on every mirror a run opened. */
function pathOf(url: string | null): string {
  if (!url) return ''

  try {
    const parsed = new URL(url)

    return parsed.pathname + parsed.search
  } catch {
    return url
  }
}

const ready = computed(() => reason.value.trim() !== '')

function preview(): void {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    try {
      count.value = await api.hidePreview({ check: props.issue.check, pattern: pattern.value })
    } catch {
      count.value = null
    }
  }, 300)
}

async function hide(): Promise<void> {
  saving.value = true

  try {
    const rule = await api.hide({
      check: props.issue.check,
      pattern: pattern.value,
      reason: reason.value,
    })

    toast.success(t('page.hidden-done'))
    emit('hidden', rule)
    open.value = false
  } catch (error) {
    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

watch(
  [open, () => props.issue.id],
  ([now]) => {
    if (!now) return

    pattern.value = pathOf(props.issue.url)
    reason.value = ''
    count.value = null
    preview()
  },
  { immediate: true },
)

watch(pattern, () => {
  if (open.value) preview()
})
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.hide-title')" :width="560">
    <div class="wx-audit-hide">
      <wx-text weight="semibold">{{ props.title }}</wx-text>
      <wx-text size="sm" tone="muted">{{ t('page.hide-help') }}</wx-text>

      <wx-form-item :label="t('page.hide-pattern')" :help="t('page.hide-pattern-help')">
        <wx-input v-model="pattern" placeholder="/search/**" />
      </wx-form-item>

      <wx-form-item :label="t('page.hide-reason')" required>
        <wx-textarea v-model="reason" :rows="3" />
      </wx-form-item>

      <wx-text v-if="count !== null" size="sm" tone="muted">{{
        t('page.hide-count', { count })
      }}</wx-text>
    </div>

    <template #footer>
      <wx-button @click="open = false">{{ t('page.dismiss') }}</wx-button>
      <wx-button type="primary" :loading="saving" :disabled="!ready" @click="hide">{{
        t('page.hide')
      }}</wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-audit-hide {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}
</style>
