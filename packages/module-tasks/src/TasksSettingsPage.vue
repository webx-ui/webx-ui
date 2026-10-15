<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
} from '@webx-ui/module-admin'
import { toast, WxActionBar, WxAlert, WxButton, WxSkeleton } from '@webx-ui/core'
import type { Patch, ScreenModel } from '@webx-ui/schema'
import { createTasksApi } from './api'
import { useTasksMessages } from './i18n'

/**
 * Where a person decides what they are told about (§6a): the bot, the chats, the events.
 *
 * The described screen `tasks.settings`, a save, and a test message — the one way to know the
 * token and the chats are right before the first real notice goes nowhere. The token is typed in
 * and never shown again: the screen says whether one is set, an empty field keeps it, and
 * forgetting it is a button of its own.
 */
defineOptions({ name: 'WxTasksSettingsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/tasks' })

const context = useAdmin()
const api = createTasksApi(context)
useTasksMessages()

const t = useTranslate('webx-tasks')
const message = useErrorText()

const TOKEN = 'tasks.telegram.token'

const values = ref<ScreenModel>({})
const snapshot = ref('')
const tokenSet = ref(false)
const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = context.can('tasks.manage')

const dirty = computed(
  () => snapshot.value !== '' && JSON.stringify(values.value) !== snapshot.value,
)

const state = computed<'saving' | 'unsaved' | 'saved'>(() =>
  saving.value ? 'saving' : dirty.value ? 'unsaved' : 'saved',
)

/* The field says whether a token is there without ever holding it. */
const patch = computed<Patch>(() => [
  { op: 'set', target: 'token', props: { placeholder: tokenSet.value ? '••••••••' : '' } },
])

function take(settings: { values: Record<string, unknown>; token_set: boolean }): void {
  values.value = { ...settings.values, [TOKEN]: '' }
  snapshot.value = JSON.stringify(values.value)
  tokenSet.value = settings.token_set
}

onMounted(async () => {
  try {
    take(await api.settings())
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
})

async function save(forget = false): Promise<void> {
  if (saving.value) return

  saving.value = true
  errors.value = {}

  try {
    take(await api.saveSettings(values.value, forget))
    toast.success(forget ? t('panel.token-forgotten') : t('panel.settings-saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors
    else toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

async function test(): Promise<void> {
  testing.value = true

  try {
    toast.success(await api.testNotice())
  } catch (error) {
    toast.danger(message(error))
  } finally {
    testing.value = false
  }
}
</script>

<template>
  <div class="wx-tasks-settings">
    <wx-screen-head :back="props.base" :back-label="t('panel.back')" :title="t('panel.settings')" />

    <wx-skeleton v-if="loading" title :rows="6" />

    <template v-else>
      <wx-alert :type="tokenSet ? 'success' : 'info'">
        {{ tokenSet ? t('panel.token-set') : t('panel.token-not-set') }}
      </wx-alert>

      <wx-screen
        v-model="values"
        name="tasks.settings"
        :patch="patch"
        :errors="errors"
        :disabled="!canManage || saving"
      />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button v-if="tokenSet" variant="outline" :disabled="saving" @click="save(true)">
          {{ t('panel.forget-token') }}
        </wx-button>
        <wx-button variant="outline" :loading="testing" :disabled="dirty" @click="test">
          {{ t('panel.send-test') }}
        </wx-button>
        <wx-button type="primary" :loading="saving" :disabled="!dirty" @click="save()">
          {{ t('panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-tasks-settings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
  container-type: inline-size;
}
</style>
