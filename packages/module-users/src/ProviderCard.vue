<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxFormItem,
  WxInput,
  WxSwitch,
} from '@webx-ui/core'
import { useErrorText, useTranslate } from '@webx-ui/module-admin'
import { copyText } from './clipboard'
import { NAMESPACE, useUsersMessages } from './i18n'
import type { ProviderCard, ProviderSave } from './types'

/**
 * One network on the settings (spec §16.3): on or off, the fields of its application, the return
 * address to copy into its console, what is missing before it is shown, and how many customers
 * sign in by it alone.
 *
 * A secret is never in the form: the server says whether it is set, and the field says so until a
 * new value is typed. «Forget» takes it away; an empty field leaves it as it is.
 */
const props = defineProps<{
  provider: ProviderCard
  disabled?: boolean
  save: (changes: ProviderSave) => Promise<void>
}>()

useUsersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const enabled = ref(false)
const options = ref<Record<string, string>>({})
const secrets = ref<Record<string, string>>({})
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

watch(
  () => props.provider,
  (provider) => {
    enabled.value = provider.enabled
    options.value = Object.fromEntries(
      provider.fields
        .filter((field) => !field.secret)
        .map((field) => [field.name, field.value ?? '']),
    )
    secrets.value = Object.fromEntries(
      provider.fields.filter((field) => field.secret).map((field) => [field.name, '']),
    )
    errors.value = {}
  },
  { immediate: true },
)

const dirty = computed(
  () =>
    enabled.value !== props.provider.enabled ||
    props.provider.fields.some((field) =>
      field.secret
        ? (secrets.value[field.name] ?? '') !== ''
        : (options.value[field.name] ?? '') !== (field.value ?? ''),
    ),
)

const labelOf = (name: string) =>
  props.provider.fields.find((field) => field.name === name)?.label ?? name

function helpOf(field: ProviderCard['fields'][number]): string | undefined {
  if (field.secret && field.set) return t('panel.secret-set')
  if (field.env) return t('panel.from-env')

  return undefined
}

async function send(changes: ProviderSave, done = t('panel.provider-saved')): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    await props.save(changes)
    toast.success(done)
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

function submit(): Promise<void> {
  const credentials = Object.fromEntries(
    Object.entries(secrets.value).filter(([, value]) => value.trim() !== ''),
  )

  return send({ enabled: enabled.value, options: { ...options.value }, credentials })
}

async function copy(): Promise<void> {
  if (await copyText(props.provider.callback)) toast.success(t('panel.copied'))
}
</script>

<template>
  <wx-card class="wx-users-provider" :title="provider.label">
    <template #extra>
      <wx-badge :type="provider.shown ? 'success' : 'default'" dot size="sm">
        {{ t(provider.shown ? 'panel.provider-shown' : 'panel.provider-hidden') }}
      </wx-badge>
    </template>

    <form class="wx-users-provider__form" @submit.prevent="submit">
      <wx-switch v-model="enabled" :disabled="disabled" :label="t('panel.provider-enabled')" />

      <wx-form-item
        v-for="field in provider.fields"
        :key="field.name"
        :label="field.label"
        :help="helpOf(field)"
        :error="errors[(field.secret ? 'credentials.' : 'options.') + field.name]?.[0]"
        :required="field.required"
      >
        <div v-if="field.secret" class="wx-users-provider__secret">
          <wx-input
            v-model="secrets[field.name]"
            type="password"
            autocomplete="new-password"
            :disabled="disabled"
            :placeholder="field.set ? '••••••••' : ''"
          />
          <wx-button
            v-if="field.set"
            variant="outline"
            :disabled="disabled || saving"
            @click="send({ forget: [field.name] })"
          >
            {{ t('panel.secret-forget') }}
          </wx-button>
        </div>
        <wx-input v-else v-model="options[field.name]" :disabled="disabled" autocomplete="off" />
      </wx-form-item>

      <wx-form-item :label="t('panel.provider-callback')" :help="t('panel.provider-callback-help')">
        <div class="wx-users-provider__secret">
          <wx-input :model-value="provider.callback" readonly />
          <wx-button variant="outline" icon="copy" @click="copy">{{ t('panel.copy') }}</wx-button>
        </div>
      </wx-form-item>

      <wx-alert
        v-if="provider.missing.length > 0"
        type="warning"
        :description="
          t('panel.provider-missing', { fields: provider.missing.map(labelOf).join(', ') })
        "
      />
      <wx-alert
        v-if="provider.only > 0"
        type="info"
        :description="t('panel.provider-only', { count: provider.only })"
      />

      <div v-if="!disabled" class="wx-users-provider__actions">
        <wx-button type="primary" native-type="submit" :loading="saving" :disabled="!dirty">
          {{ t('panel.save') }}
        </wx-button>
      </div>
    </form>
  </wx-card>
</template>

<style scoped>
.wx-users-provider__form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-users-provider__secret {
  display: flex;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-users-provider__secret > :first-child {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-users-provider__actions {
  display: flex;
  justify-content: flex-end;
}
</style>
