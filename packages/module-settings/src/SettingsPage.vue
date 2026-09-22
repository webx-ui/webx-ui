<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxBackupNote,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { toast, WxActionBar, WxButton, WxSkeleton } from '@webx-ui/core'
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
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

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
    toast.danger(message(error))
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

    // Some of what this screen holds is also what the panel is wearing — the logo in the
    // corner, the mark on the rail, the name of the site. The manifest is where the shell
    // reads those, so saving them here has to hand the shell a new one; otherwise the logo
    // somebody has just replaced goes on being the old one until the page is reloaded.
    void context.refreshManifest()
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('page.failed'))
    } else {
      toast.danger(message(error, t('page.failed')))
    }
  } finally {
    saving.value = false
  }
}
/* Saving, and again in the bar below: the head goes with the scroll on a form this long (§3.3). */
const actions = computed<ScreenAction[]>(() =>
  canManage
    ? [
        {
          key: 'save',
          label: t('page.save'),
          primary: true,
          loading: saving.value,
          run: () => void save(),
        },
      ]
    : [],
)
</script>

<template>
  <div class="wx-settings">
    <wx-screen-head :title="title" :actions="actions" />

    <wx-skeleton v-if="loading" :rows="4" />
    <wx-screen
      v-else
      v-model="values"
      name="settings.index"
      :errors="errors"
      :disabled="!canManage"
    />

    <!--
      Where the panel says when the database was last dumped (§6 of the backups spec). It is
      the section this belongs in — System is Settings and Administrators, and of those two
      this is the one somebody opens to ask how the installation is doing — and `settings.view`
      is the right the line itself asks for. A footnote under the form rather than a field in
      it: the form is what the site is, and this is what the server did last night.
    -->
    <wx-backup-note />

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
  gap: var(--wx-gap, var(--wx-space-16));
}
</style>
