<script setup lang="ts">
import { ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { toast, useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { createUsersApi } from './api'
import { NAMESPACE, useUsersMessages } from './i18n'
import type { GroupRow } from './types'

/**
 * One customer group, written (decision 24): a code the rules and the site's code name it by, and
 * a name in one language — no translations, no order, no «default».
 */
const props = defineProps<{ group: GroupRow | null }>()

const { resolve, dismiss, open } = useModal<GroupRow>()

const api = createUsersApi(useAdmin())
useUsersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const code = ref('')
const name = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

watch(
  () => props.group,
  (group) => {
    code.value = group?.code ?? ''
    name.value = group?.name ?? ''
    errors.value = {}
  },
  { immediate: true },
)

const errorOf = (field: string): string | undefined => errors.value[field]?.[0]

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  const input = { code: code.value.trim(), name: name.value.trim() }

  try {
    resolve(
      props.group === null
        ? await api.createGroup(input)
        : await api.saveGroup(props.group.id, input),
    )
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="group ? t('panel.group-edit') : t('panel.groups-new')"
    :width="480"
  >
    <form class="wx-users-group-form" @submit.prevent="save">
      <wx-form-item :label="t('panel.group-name')" :error="errorOf('name')" required>
        <wx-input v-model="name" :maxlength="255" autofocus />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.group-code')"
        :help="t('panel.group-code-help')"
        :error="errorOf('code')"
        required
      >
        <wx-input v-model="code" :maxlength="64" placeholder="wholesale" />
      </wx-form-item>
    </form>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">{{ t('panel.save') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-users-group-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
