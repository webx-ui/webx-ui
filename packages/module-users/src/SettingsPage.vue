<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast, WxActionBar, WxAlert, WxButton, WxCard, WxHeading, WxSkeleton } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate, WxScreen, WxScreenHead } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createUsersApi } from './api'
import { NAMESPACE, useUsersMessages } from './i18n'
import ProviderCard from './ProviderCard.vue'
import type { ProviderSave, UsersSettings } from './types'

/**
 * «Customers → Settings» (spec §16.3): the registration and how much the forms ask for — the
 * screen `users.settings`, saved with the button below it — then a card per social network, each
 * saved on its own, then where the letters go. Behind `users.settings` alone.
 */
defineOptions({ name: 'WxUsersSettingsPage' })

withDefaults(defineProps<{ base?: string }>(), { base: '/users' })

const admin = useAdmin()
const api = createUsersApi(admin)
useUsersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const settings = ref<UsersSettings | null>(null)
const values = ref<ScreenModel>({})
const errors = ref<Record<string, string[]>>({})
const loading = ref(true)
const saving = ref(false)

const canSave = computed(() => admin.can('users.settings'))

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'users-settings')?.title ??
    t('module.settings'),
)

function take(answer: UsersSettings): void {
  settings.value = answer
  values.value = { ...answer.values }
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

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    take(await api.saveSettings(values.value))
    toast.success(t('panel.settings-saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors

    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

/** A card's save answers the whole page, and only the cards are taken from it. */
async function saveProvider(key: string, changes: ProviderSave): Promise<void> {
  const answer = await api.saveProvider(key, changes)

  if (settings.value) settings.value = { ...settings.value, providers: answer.providers }
}
</script>

<template>
  <div class="wx-users-settings">
    <wx-screen-head :title="title" divider />

    <template v-if="loading">
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else-if="settings">
      <wx-screen
        v-model="values"
        name="users.settings"
        :errors="errors"
        :disabled="!canSave || !settings.stored"
      />

      <wx-action-bar v-if="canSave && settings.stored">
        <wx-button type="primary" :loading="saving" @click="save">{{ t('panel.save') }}</wx-button>
      </wx-action-bar>

      <section v-if="settings.providers.length > 0" class="wx-users-settings__section">
        <wx-heading :level="2" size="md">{{ t('panel.providers') }}</wx-heading>
        <p class="wx-users-settings__help">{{ t('panel.providers-help') }}</p>

        <div class="wx-users-settings__providers">
          <provider-card
            v-for="provider in settings.providers"
            :key="provider.key"
            :provider="provider"
            :disabled="!canSave"
            :save="(changes: ProviderSave) => saveProvider(provider.key, changes)"
          />
        </div>
      </section>

      <wx-card :title="t('panel.mail')">
        <wx-alert
          :type="settings.mail.reaches ? 'info' : 'warning'"
          :description="
            t(settings.mail.reaches ? 'panel.mail-reaches' : 'panel.mail-log', {
              transport: settings.mail.transport,
            })
          "
        />
      </wx-card>
    </template>
  </div>
</template>

<style scoped>
.wx-users-settings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-users-settings__section {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-users-settings__help {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-users-settings__providers {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--wx-gap, var(--wx-space-16));
}

@container (min-width: 960px) {
  .wx-users-settings__providers {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
