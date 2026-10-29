<script setup lang="ts">
import { computed, ref } from 'vue'
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
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { BoardGroup, StatusBoardData } from './types'

/**
 * One public status written (spec §11.4): what the buyer sees — a name and one line of
 * description in every language of the site — and the code templates and agents know it by,
 * which renaming the status leaves as it is.
 */
const props = defineProps<{ group: BoardGroup | null }>()

const { resolve, dismiss, open } = useModal<StatusBoardData>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const code = ref(props.group?.code ?? '')
const name = ref<LocalizedValue>({ ...(props.group?.name ?? {}) })
const description = ref<LocalizedValue>({ ...(props.group?.description ?? {}) })
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

/** A language's error comes as `name.en`; the field shows the first of them. */
function errorOf(field: string): string | undefined {
  const key = Object.keys(errors.value).find((one) => one === field || one.startsWith(`${field}.`))

  return key ? errors.value[key]?.[0] : undefined
}

const codeError = computed(() => errorOf('code'))

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    resolve(
      await api.savePublic(props.group?.id ?? null, {
        code: code.value.trim(),
        name: name.value as Record<string, string>,
        description: description.value as Record<string, string>,
      }),
    )
  } catch (failure) {
    const found = (failure as { errors?: Record<string, string[]> }).errors

    if (found && Object.keys(found).length > 0) errors.value = found
    else toast.danger(message(failure))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="group ? t('panel.statuses.edit-public') : t('panel.statuses.new-public')"
    :width="520"
  >
    <div class="wx-orders-public-form">
      <wx-form-item :label="t('panel.statuses.name')" :error="errorOf('name')" required>
        <wx-input v-model="name" localized :maxlength="120" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.statuses.description')"
        :help="t('panel.statuses.description-help')"
        :error="errorOf('description')"
      >
        <wx-input v-model="description" localized :maxlength="255" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.statuses.code')"
        :help="t('panel.statuses.code-help')"
        :error="codeError"
        required
      >
        <wx-input v-model="code" placeholder="in_progress" :maxlength="48" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">{{ t('panel.save') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-public-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
