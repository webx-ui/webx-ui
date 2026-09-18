<script setup lang="ts">
import { ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  type LocalizedValue,
} from '@webx-ui/core'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import type { InboxForm } from './types'

/**
 * A form, started.
 *
 * Two fields, because a form has nothing else until it has questions: what it is called and
 * where it answers. Everything else — who is written to, what happens after sending, the
 * antispam — belongs to the editor, and asking for it before the form exists would be a
 * dialog longer than the screen it leads to.
 *
 * The address is typed rather than made out of the title. A page's address is a sentence
 * transliterated; a form's is a short word somebody chooses once and then writes into a
 * template by hand, and guessing `svyazhites-s-nami` for them helps nobody.
 */
const { resolve, dismiss, open } = useModal<InboxForm>()

const context = useAdmin()
const api = createInboxApi(context)
useInboxMessages()

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const title = ref<LocalizedValue>({})
const slug = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    resolve(
      await api.createForm({
        title: title.value,
        slug: slug.value.trim(),
        is_enabled: true,
        options: {},
      }),
    )
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('panel.failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new-form-title')" :width="520">
    <div class="wx-inbox-create">
      <wx-form-item :label="t('panel.title')" :error="errorOf('title')" required>
        <wx-input v-model="title" localized autofocus />
      </wx-form-item>

      <wx-form-item :label="t('panel.slug')" :help="t('panel.slug-help')" :error="errorOf('slug')">
        <wx-input v-model="slug" placeholder="contact" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">
          {{ t('panel.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-inbox-create {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
