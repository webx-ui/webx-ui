<script setup lang="ts">
import { computed } from 'vue'
import { WxDescriptions, WxDescriptionsItem, WxText } from '@webx-ui/core'
import { useTranslate, WxDate } from '@webx-ui/module-admin'
import { useUserEditor } from './editor'
import { NAMESPACE, useUsersMessages } from './i18n'

/**
 * `wx-users-state` — the account at a glance on the card (spec §16.2): registered, the last
 * sign-in, blocked since, the language of the letters. Read from the open customer, not from the
 * model: none of it is edited here.
 */
defineOptions({ name: 'WxUsersState' })

useUsersMessages()

const t = useTranslate(NAMESPACE)
const editor = useUserEditor()
const user = computed(() => editor?.user.value ?? null)
</script>

<template>
  <wx-descriptions v-if="user" class="wx-users-state" :columns="1" size="sm">
    <wx-descriptions-item :label="t('panel.registered')">
      <wx-date :value="user.created_at" />
    </wx-descriptions-item>
    <wx-descriptions-item :label="t('panel.last-sign-in')">
      <wx-date v-if="user.last_sign_in_at" :value="user.last_sign_in_at" />
      <wx-text v-else size="sm" tone="muted">{{ t('panel.never') }}</wx-text>
    </wx-descriptions-item>
    <wx-descriptions-item v-if="user.blocked_at" :label="t('panel.blocked-since')">
      <wx-date :value="user.blocked_at" />
    </wx-descriptions-item>
    <wx-descriptions-item v-if="user.locale" :label="t('panel.language')">
      {{ user.locale }}
    </wx-descriptions-item>
  </wx-descriptions>
</template>
