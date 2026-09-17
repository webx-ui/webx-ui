<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAdmin, useTranslate, WxScreen } from '@webx-ui/module-admin'
import { toast, WxActionBar, WxButton, WxHeading, WxSkeleton } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createSettingsApi } from './api'
import { useSettingsMessages } from './i18n'

/**
 * The settings section: the screen the server describes, and the saving that is the page's own
 * — in the head, and again in the bar along the bottom, because the form is taller than a
 * window and the head goes with the scroll. The screen does not know how it is saved; this does.
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

    <!-- The settings are longer than a window, and the button in the head is off the top of it
         by the second group of fields. This is the same button, where the eye already is. -->
    <wx-action-bar v-if="canManage && !loading">
      <wx-button type="primary" :loading="saving" @click="save">{{ t('page.save') }}</wx-button>
    </wx-action-bar>
  </div>
</template>

<style scoped>
/* A column, because the bar along the bottom is pushed there by an auto margin — and it is
   `WxMain` that gives a screen carrying one the height to push it down through. */
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
