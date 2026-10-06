<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxScreen } from '@webx-ui/module-admin'
import { toast, WxButton, WxCard, WxSkeleton, WxText } from '@webx-ui/core'
import { useAuthMessages } from './i18n'

/** The values as the screen holds them, keyed by field name. */
type Values = Record<string, unknown>

/**
 * The site's house rules for content — tone, what it never says, notes — beside the place
 * agents are connected, since agents are who reads them (`settings://content-rules`).
 *
 * They are `module-settings`' values on its own screen; this card shows only when that module is
 * installed and says which screen it is, and only to whoever may see the settings. Saving needs
 * the right to change them; without it the card is read-only. The card is this component's, not
 * the screen's — its root is a plain column — so «Save» sits in the card's own footer.
 */
const admin = useAdmin()
useAuthMessages()

const t = useTranslate('webx-auth')
const panel = useTranslate('webx-admin')
const message = useErrorText()

const screen = computed<string | null>(() => {
  const meta = admin.state.manifest?.modules.find((module) => module.id === 'settings')?.meta
  const name = meta?.content_screen

  return typeof name === 'string' ? name : null
})

const canSee = admin.can('settings.view') || admin.can('settings.manage')
const canManage = admin.can('settings.manage')

const values = ref<Values>({})
const errors = ref<Record<string, string[]>>({})
const loading = ref(true)
const saving = ref(false)

const endpoint = `${admin.apiPath}/settings/content`

onMounted(async () => {
  if (!screen.value || !canSee) return

  try {
    const body = await admin.http.get<{ data: { values: Values } }>(endpoint)

    values.value = body.data.values
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
    const body = await admin.http.put<{ data: { values: Values } }>(endpoint, {
      values: values.value,
    })

    values.value = body.data.values
    toast.success(panel('editor.saved'))
  } catch (error) {
    const answer = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (answer?.errors) errors.value = answer.errors

    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-card v-if="screen && canSee" :title="t('connect.rules')" class="wx-content-rules">
    <wx-skeleton v-if="loading" :rows="4" />
    <wx-screen v-else v-model="values" :name="screen" :errors="errors" :disabled="!canManage" />

    <template v-if="!loading" #footer>
      <div class="wx-content-rules__foot">
        <wx-text size="sm" tone="muted">{{ t('connect.rules-hint') }}</wx-text>
        <wx-button v-if="canManage" type="primary" :loading="saving" @click="save">{{
          panel('editor.save')
        }}</wx-button>
      </div>
    </template>
  </wx-card>
</template>

<style scoped>
.wx-content-rules__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
}
</style>
