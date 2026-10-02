<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxScreen } from '@webx-ui/module-admin'
import { toast, WxActionBar, WxButton, WxSkeleton } from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import AuditLayout from './AuditLayout.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'

/**
 * The section's settings (§8): where the site is and which hosts are its stands, the limits of
 * the crawl and the paths it leaves out, the thresholds, the nightly run and how much history
 * to keep. The form is the screen the server describes (`audit.settings`), so a project patches
 * a field in the way it does everywhere else; saving is this page's own.
 */
const props = defineProps<{ base: string }>()

const context = useAdmin()
const api = createAuditApi(context)
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()

const values = ref<ScreenModel>({})
const errors = ref<Record<string, string[]>>({})
const loading = ref(true)
const saving = ref(false)

// A boolean, not a computed: the permissions do not change while the screen is open.
const canManage = context.can('audit.manage')

onMounted(async () => {
  try {
    values.value = (await api.settings()) as ScreenModel
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
})

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    values.value = (await api.saveSettings(values.value)) as ScreenModel
    toast.success(t('page.saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors

    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <audit-layout :base="props.base" current="settings" :card="false">
    <div class="wx-audit-settings">
      <wx-skeleton v-if="loading" :rows="6" />
      <wx-screen
        v-else
        v-model="values"
        name="audit.settings"
        :errors="errors"
        :disabled="!canManage"
      />

      <wx-action-bar v-if="canManage && !loading">
        <wx-button type="primary" :loading="saving" @click="save">{{ t('page.save') }}</wx-button>
      </wx-action-bar>
    </div>
  </audit-layout>
</template>

<style scoped>
.wx-audit-settings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
}
</style>
