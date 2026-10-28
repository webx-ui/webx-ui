<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxButton, WxIcon, WxText, WxTooltip } from '@webx-ui/core'
import { useAdmin, useDates, useTranslate } from '@webx-ui/module-admin'
import { useUserEditor } from './editor'
import { NAMESPACE, useUsersMessages } from './i18n'

/**
 * `wx-users-ways` — how the customer signs in (spec §16.2, §9.6): the password and every linked
 * network with its address and date, «Unlink» on each. The last way to sign in cannot be unlinked,
 * here as in the customer's own account; the server holds the rule, the button only says it.
 */
defineOptions({ name: 'WxUsersWays' })

useUsersMessages()

const admin = useAdmin()
const t = useTranslate(NAMESPACE)
const dates = useDates()
const editor = useUserEditor()
const user = computed(() => editor?.user.value ?? null)
const canManage = computed(() => admin.can('users.manage') && !(editor?.locked.value ?? true))
</script>

<template>
  <div v-if="user" class="wx-users-ways">
    <wx-text class="wx-users-ways__title" size="sm" weight="medium">{{ t('panel.ways') }}</wx-text>

    <wx-text v-if="user.ways.length === 0" size="sm" tone="muted">
      {{ t('panel.ways-empty') }}
    </wx-text>

    <ul v-else class="wx-users-ways__list">
      <li v-for="way in user.ways" :key="way.key" class="wx-users-ways__row">
        <wx-icon
          class="wx-users-ways__icon"
          :name="way.key === 'password' ? 'lock' : 'link'"
          size="sm"
        />
        <div class="wx-users-ways__body">
          <span class="wx-users-ways__name">
            {{ way.label }}
            <wx-tooltip v-if="!way.shown" :content="t('panel.way-off')">
              <wx-badge size="sm" variant="outline">{{ t('panel.provider-hidden') }}</wx-badge>
            </wx-tooltip>
          </span>
          <span v-if="way.email || way.linked_at" class="wx-users-ways__meta">
            <span v-if="way.email" class="wx-users-ways__email">{{ way.email }}</span>
            <span v-if="way.linked_at">{{
              t('panel.linked', { date: dates.short(way.linked_at) })
            }}</span>
          </span>
        </div>
        <wx-tooltip
          v-if="way.key !== 'password' && canManage"
          :content="way.can_unlink ? '' : t('panel.unlink-last')"
          :disabled="way.can_unlink"
        >
          <wx-button
            size="sm"
            variant="outline"
            :disabled="!way.can_unlink"
            @click="editor?.unlink(way.key)"
          >
            {{ t('panel.unlink') }}
          </wx-button>
        </wx-tooltip>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.wx-users-ways {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-users-ways__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-users-ways__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
}

.wx-users-ways__icon {
  flex: none;
  color: var(--wx-text-muted);
}

.wx-users-ways__body {
  display: flex;
  flex: 1 1 12rem;
  flex-direction: column;
  min-width: 0;
}

.wx-users-ways__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-users-ways__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4) var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-users-ways__email {
  overflow-wrap: anywhere;
}
</style>
