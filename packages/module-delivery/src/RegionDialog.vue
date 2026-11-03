<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import {
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { RegionRow } from './types'

/**
 * One region of a country, made or changed by hand (decision 42): its name in the languages of
 * the site and its code. «Add a region» and «Edit» are this one dialog — the shape every
 * dictionary of the panel is edited in (Catalog → property values) — and not a form over the list
 * plus a field that turns into an input in the row.
 *
 * Resolves with `true` once saved.
 */
defineOptions({ name: 'WxDeliveryRegionDialog' })

const props = withDefaults(defineProps<{ country: string; region?: RegionRow | null }>(), {
  region: null,
})

const { open, resolve } = useModal<boolean>()

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()
const message = useErrorText()

const names = ref<LocalizedValue>({ ...(props.region?.names ?? {}) })
const code = ref(props.region?.code ?? '')
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

const written = () => Object.values(names.value).some((line) => line.trim() !== '')

function errorOf(key: string): string | undefined {
  const found = Object.keys(errors.value).find((one) => one === key || one.startsWith(`${key}.`))

  return found === undefined ? undefined : errors.value[found]?.[0]
}

async function submit(): Promise<void> {
  if (!written() || saving.value) return

  saving.value = true
  errors.value = {}

  const clean = Object.fromEntries(
    Object.entries(names.value)
      .map(([locale, line]) => [locale, line.trim()])
      .filter(([, line]) => line !== ''),
  )

  try {
    if (props.region) {
      await api.saveRegion(props.region.id, { names: clean, code: code.value.trim() || null })
    } else {
      await api.createRegion(props.country, clean, code.value.trim() || null)
    }

    resolve(true)
  } catch (error) {
    errors.value = (error as { errors?: Record<string, string[]> }).errors ?? {}
    if (Object.keys(errors.value).length === 0) errors.value = { names: [message(error)] }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="props.region ? t('region.edit-title') : t('region.new-title')"
    :width="480"
  >
    <wx-form-item
      :label="t('region.name')"
      :help="t('locality.names-help')"
      :error="errorOf('names')"
      required
    >
      <div ref="field">
        <wx-input
          v-model="names"
          localized
          :maxlength="120"
          :aria-label="t('region.name')"
          data-test="region-name"
          @keyup.enter="submit"
        />
      </div>
    </wx-form-item>

    <wx-form-item :label="t('region.code')" :help="t('region.code-help')" :error="errorOf('code')">
      <wx-input v-model="code" :maxlength="8" @keyup.enter="submit" />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="open = false">{{ t('common.cancel') }}</wx-button>
        <wx-button
          type="primary"
          :loading="saving"
          :disabled="!written()"
          data-test="region-save"
          @click="submit"
        >
          {{ props.region ? t('common.save') : t('common.add') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
