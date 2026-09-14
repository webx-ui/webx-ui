<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAdmin, useTranslate, WxScreen } from '@webx-ui/module-admin'
import { toast, WxButton, WxHeading, WxSkeleton } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createSettingsApi } from './api'
import { useSettingsMessages } from './i18n'

/**
 * The settings section: the screen the server describes, and the one button that is the
 * page's own. The screen does not know how it is saved; this does.
 */
const context = useAdmin()
const api = createSettingsApi(context)
useSettingsMessages()

const t = useTranslate('webx-settings')

const values = ref<ScreenModel>({})
const errors = ref<Record<string, string[]>>({})
const loading = ref(true)
const saving = ref(false)

const canManage = context.can('settings.manage')

/** The section's name, as the server translated it; the built-in English until it arrives. */
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'settings')?.title ??
    t('module.title'),
)

onMounted(async () => {
  try {
    values.value = await api.load()
  } catch (error) {
    const body = (error as { body?: { message?: string } }).body
    toast.danger(body?.message ?? String(error))
  } finally {
    loading.value = false
  }
})

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    values.value = await api.save(values.value)
    toast.success(t('page.saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('page.failed'))
    } else {
      toast.danger(body?.message ?? t('page.failed'))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="wx-settings">
    <div class="wx-settings__head">
      <wx-heading :level="2">{{ title }}</wx-heading>
      <wx-button v-if="canManage" type="primary" :loading="saving" @click="save">
        {{ t('page.save') }}
      </wx-button>
    </div>

    <wx-skeleton v-if="loading" :rows="4" />
    <wx-screen
      v-else
      v-model="values"
      name="settings.index"
      :errors="errors"
      :disabled="!canManage"
    />
  </div>
</template>

<style scoped>
.wx-settings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-settings__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
}
</style>
