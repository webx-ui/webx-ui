<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { WxLink } from '@webx-ui/core'
import { useAdmin, useTranslate, WxRelationsField } from '@webx-ui/module-admin'
import { useNotificationsMessages } from './i18n'

/**
 * `wx-notifications-channels`: the channels a module notifies through, for one of its events
 * (§15.2) — «Notify through» of a form of the Inbox, and whatever comes after it.
 *
 * Chosen with search, several at once, in any order: the value is the ids and nothing else, kept
 * in the consumer's own data (decision 9). What there is to choose from is the target of the event
 * (`notify-<event>`): the channels of that event and the ones for any event, never the bin. A
 * channel switched off stays chosen and is marked — it sends nothing until it is on again.
 *
 * Choosing needs no right of this module: the picker shows a name and a kind, never recipients or
 * secrets. "Set up channels" is there for whoever may.
 */
defineOptions({ name: 'WxNotificationChannelsField' })

const props = withDefaults(
  defineProps<{
    /** The event the channels are for: `inbox.submission`. */
    event: string
    disabled?: boolean
    /** Where the section is in the panel, for the link to it. */
    base?: string
    addText?: string
    emptyText?: string
  }>(),
  { disabled: false, base: '/notifications', addText: undefined, emptyText: undefined },
)

const value = defineModel<number[] | null>({ default: () => [] })

useNotificationsMessages()

const admin = useAdmin()
const t = useTranslate('webx-notifications')

/** The address of the event's target: `[a-z0-9-]+`, so the dots and underscores become dashes. */
const target = computed(() => `notify-${props.event.replace(/[._]/g, '-')}`)

const canManage = computed(() => admin.can('notifications.manage'))

const router = useRouter()

const link = computed(() => `${admin.basePath}${props.base}`.replace(/\/{2,}/g, '/'))

/* Inside the panel, without loading it again: the address is for a middle click. */
function go(event: MouseEvent): void {
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return

  event.preventDefault()
  void router.push(props.base)
}
</script>

<template>
  <div class="wx-notification-channels">
    <wx-relations-field
      v-model="value"
      :target="target"
      :sortable="false"
      :disabled="props.disabled"
      :add-text="props.addText ?? t('panel.pick-channels')"
      :empty-text="props.emptyText"
    />
    <wx-link v-if="canManage" :href="link" size="sm" @click="go">{{
      t('panel.set-up-channels')
    }}</wx-link>
  </div>
</template>

<style scoped>
.wx-notification-channels {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-notification-channels > :first-child {
  align-self: stretch;
}
</style>
