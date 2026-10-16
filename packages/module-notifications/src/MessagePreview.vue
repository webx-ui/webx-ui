<script setup lang="ts">
import { computed } from 'vue'
import {
  localizedValue,
  useLocales,
  WxAlert,
  WxCard,
  WxText,
  type LocalizedValue,
} from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useChannel } from './context'
import { useNotificationsMessages } from './i18n'
import { examplesOf, renderMessage, TemplateRefusal, type RenderedMessage } from './render'
import { telegramHtml } from './telegram'

/**
 * The message as it goes out, drawn while the template is typed (§7.5): the event's examples
 * through the provider's escaping, by the renderer that `PreviewParityTest` holds to the server's.
 * Telegram as a bubble of the chat, with only the tags Telegram takes; a letter as its subject and
 * its text.
 *
 * The language is the one the editor is on, so switching it switches the preview with it.
 */
defineOptions({ name: 'WxNotificationsPreview' })

useNotificationsMessages()

const t = useTranslate('webx-notifications')
const channel = useChannel()
const locales = useLocales()

const locale = computed(() => locales.active.value || locales.list.value[0]?.code || '')

type Outcome = { message: RenderedMessage } | { refusal: string }

const outcome = computed<Outcome | null>(() => {
  const provider = channel.provider.value

  if (provider === null) return null

  const values = channel.values.value

  try {
    return {
      message: renderMessage({
        provider: provider.key,
        template: localizedValue(values.template as LocalizedValue | string | null, locale.value),
        subject: localizedValue(values.subject as LocalizedValue | string | null, locale.value),
        hasSubject: provider.subject,
        limit: provider.limit,
        values: examplesOf(channel.event.value),
        event: channel.event.value,
      }),
    }
  } catch (error) {
    if (error instanceof TemplateRefusal) {
      return { refusal: t(`errors.${error.key}`, error.replace) }
    }

    throw error
  }
})

const message = computed(() =>
  outcome.value !== null && 'message' in outcome.value ? outcome.value.message : null,
)

const refusal = computed(() =>
  outcome.value !== null && 'refusal' in outcome.value ? outcome.value.refusal : null,
)

/* Built here: a `}}` of Mustache inside an interpolation of the template would end it. */
const unknownLine = computed(() =>
  t('panel.preview-unknown', {
    names: (message.value?.unknown ?? []).map((name) => '{{' + name + '}}').join(', '),
  }),
)

const telegram = computed(() => channel.provider.value?.key === 'telegram')

const bubble = computed(() =>
  message.value === null || message.value.truncated ? '' : telegramHtml(message.value.body),
)
</script>

<template>
  <wx-card :title="t('panel.preview')" class="wx-notifications-preview">
    <wx-text size="sm" tone="muted">
      {{ channel.event.value ? t('panel.preview-help') : t('panel.preview-no-event') }}
    </wx-text>

    <wx-alert
      v-if="refusal"
      type="danger"
      variant="soft"
      :title="t('panel.preview-refused')"
      :description="refusal"
    />

    <template v-else-if="message">
      <div v-if="telegram" class="wx-notifications-preview__chat">
        <!-- eslint-disable-next-line vue/no-v-html -- only Telegram's tags, see telegram.ts -->
        <div v-if="!message.truncated" class="wx-notifications-preview__bubble" v-html="bubble" />
        <div v-else class="wx-notifications-preview__bubble">{{ message.plain }}</div>
      </div>

      <div v-else class="wx-notifications-preview__letter">
        <div class="wx-notifications-preview__subject">
          <wx-text size="sm" tone="muted">{{ t('panel.preview-subject') }}</wx-text>
          <wx-text weight="medium">{{ message.subject || '—' }}</wx-text>
        </div>
        <div class="wx-notifications-preview__body">{{ message.body }}</div>
      </div>

      <wx-text v-if="message.plain.trim() === ''" size="sm" tone="muted">
        {{ t('panel.preview-empty') }}
      </wx-text>

      <wx-alert
        v-if="message.truncated"
        type="warning"
        variant="soft"
        :description="
          t('panel.preview-truncated', { limit: String(channel.provider.value?.limit ?? '') })
        "
      />

      <wx-text v-if="message.unknown.length > 0 && channel.event.value" size="sm" tone="muted">
        {{ unknownLine }}
      </wx-text>
    </template>
  </wx-card>
</template>

<style scoped>
.wx-notifications-preview__chat {
  display: flex;
  margin-top: var(--wx-space-8);
  padding: var(--wx-space-12);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

/*
 * A bubble of the chat: as wide as its text and no wider than most of the column, the way
 * Telegram draws a message from a bot.
 */
.wx-notifications-preview__bubble {
  max-width: min(100%, 520px);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-sm);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

.wx-notifications-preview__bubble :deep(a) {
  color: var(--wx-text-link);
}

.wx-notifications-preview__bubble :deep(code),
.wx-notifications-preview__bubble :deep(pre) {
  font-family: var(--wx-font-family-mono);
}

.wx-notifications-preview__bubble :deep(blockquote) {
  margin: 0;
  padding-left: var(--wx-space-8);
  border-left: 3px solid var(--wx-color-primary);
}

.wx-notifications-preview__bubble :deep(.tg-spoiler) {
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-fill);
}

.wx-notifications-preview__letter {
  display: flex;
  flex-direction: column;
  margin-top: var(--wx-space-8);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
  overflow: hidden;
}

.wx-notifications-preview__subject {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-muted);
  background: var(--wx-bg-subtle);
  overflow-wrap: anywhere;
}

.wx-notifications-preview__body {
  padding: var(--wx-space-12);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}
</style>
