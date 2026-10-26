<script setup lang="ts">
import { ref, useId, watch } from 'vue'
import { slugify, useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { toast, useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { createConfiguratorsApi } from './api'
import { useConfiguratorsMessages } from './i18n'
import type { ConfiguratorDetail } from './types'

/**
 * A new configurator: a name and a key, nothing else — the steps are built in the editor it
 * opens in. The key follows the name until somebody types one of their own; it is what a block
 * on the site will name the configurator by (decision 11).
 */
defineOptions({ name: 'WxConfiguratorCreateDialog' })

const { open, resolve, dismiss } = useModal<ConfiguratorDetail>()

const context = useAdmin()
const api = createConfiguratorsApi(context)
useConfiguratorsMessages()

const t = useTranslate('webx-configurator')
const message = useErrorText()

const formId = useId()
const title = ref('')
const key = ref('')
const typed = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

watch(title, (name) => {
  if (!typed.value) key.value = slugify(name).slice(0, 64)
})

function onKey(value: string | number | Record<string, string> | undefined): void {
  key.value = String(value ?? '')
  typed.value = key.value !== ''
}

async function submit(): Promise<void> {
  if (saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(await api.create(title.value.trim(), key.value.trim()))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors
    else toast.danger(message(error))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('configurator.create-title')" :width="480">
    <form :id="formId" class="wx-configurator-create" @submit.prevent="submit">
      <wx-form-item :label="t('configurator.field-title')" :error="errors.title" required>
        <wx-input v-model="title" :maxlength="255" />
      </wx-form-item>

      <wx-form-item
        :label="t('configurator.field-key')"
        :help="t('configurator.key-help')"
        :error="errors.key"
        required
      >
        <wx-input :model-value="key" :maxlength="64" @update:model-value="onKey" />
      </wx-form-item>
    </form>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('configurator.cancel') }}</wx-button>
        <wx-button type="primary" native-type="submit" :form="formId" :loading="saving">
          {{ t('configurator.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-configurator-create {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}
</style>
