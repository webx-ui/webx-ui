<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast, WxActionBar, WxButton, WxCard, WxSkeleton } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate, WxScreen, WxScreenHead } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { provideOrdersSettings, sampleNumber } from './settings'
import type { OrdersSettings } from './types'

/**
 * «Orders → Settings» (spec §4, §5.2, §11.4): the format of the number with the trial number
 * under it, and the statuses a new order comes into — the screen `orders.settings`, saved with
 * the button below it. Without the settings module the screen says the config decides and there
 * is nothing to save. Behind `orders.statuses` alone.
 */
defineOptions({ name: 'WxOrdersSettingsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/orders' })

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const settings = ref<OrdersSettings | null>(null)
const values = ref<ScreenModel>({})
const errors = ref<Record<string, string[]>>({})
const loading = ref(true)
const saving = ref(false)

const canSave = computed(() => admin.can('orders.statuses') && settings.value?.stored === true)

provideOrdersSettings({
  sample: computed(() =>
    settings.value ? sampleNumber(values.value, settings.value.next_id) : '',
  ),
})

function take(answer: OrdersSettings): void {
  settings.value = answer
  values.value = { ...answer.values } as ScreenModel
}

onMounted(async () => {
  try {
    take(await api.settings())
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    loading.value = false
  }
})

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    take(await api.saveSettings(values.value))
    toast.success(t('panel.settings-saved'))
  } catch (failure) {
    const found = (failure as { errors?: Record<string, string[]> }).errors

    if (found) errors.value = found

    toast.danger(message(failure))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="wx-orders-settings">
    <wx-screen-head
      :title="t('panel.settings-title')"
      :back="`${props.base}/statuses`"
      :back-label="t('panel.to-statuses')"
      divider
    />

    <wx-card v-if="loading"><wx-skeleton :rows="6" /></wx-card>

    <template v-else-if="settings">
      <wx-screen v-model="values" name="orders.settings" :errors="errors" :disabled="!canSave" />

      <wx-action-bar v-if="canSave">
        <wx-button type="primary" :loading="saving" @click="save">{{ t('panel.save') }}</wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-orders-settings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}
</style>
