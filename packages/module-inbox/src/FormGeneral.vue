<script setup lang="ts">
import { useTranslate } from '@webx-ui/module-admin'
import { WxCard, WxFormItem, WxInput, WxSwitch, type LocalizedValue } from '@webx-ui/core'
import LocalizedRichText from './LocalizedRichText.vue'
import { option } from './options'
import type { FormOptions } from './types'

/**
 * What the form is, and what it says once it has been sent.
 *
 * The two halves are on two cards because they are read at two different times: the first is
 * set up once, the second is the sentence a visitor sees and is rewritten whenever the
 * marketing does.
 */
defineProps<{ errors: Record<string, string[]> }>()

const settings = defineModel<{ slug: string; title: LocalizedValue; is_enabled: boolean }>(
  'settings',
  { required: true },
)

const options = defineModel<FormOptions>('options', { required: true })

const t = useTranslate('webx-inbox')

const heading = option<LocalizedValue>(options, 'thank-you.heading', {})
const text = option<LocalizedValue>(options, 'thank-you.text', {})
const submit = option<LocalizedValue>(options, 'design.submit-text', {})
const redirect = option<string>(options, 'redirect', '')
</script>

<template>
  <div class="wx-inbox-general">
    <wx-card>
      <wx-form-item :label="t('panel.title')" :error="errors.title?.[0]" required>
        <wx-input v-model="settings.title" localized />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.slug')"
        :help="t('panel.slug-help')"
        :error="errors.slug?.[0]"
        required
      >
        <wx-input v-model="settings.slug" placeholder="contact" />
      </wx-form-item>

      <wx-form-item :label="t('panel.is-enabled')" :help="t('panel.is-enabled-help')">
        <wx-switch v-model="settings.is_enabled" />
      </wx-form-item>
    </wx-card>

    <wx-card :title="t('panel.after-sending')">
      <wx-form-item :label="t('panel.submit-text')">
        <wx-input v-model="submit" localized />
      </wx-form-item>

      <wx-form-item :label="t('panel.thank-you-heading')">
        <wx-input v-model="heading" localized />
      </wx-form-item>

      <!-- Printed raw on the site, which is why it is written where formatting is possible. -->
      <wx-form-item :label="t('panel.thank-you-text')">
        <localized-rich-text v-model="text" />
      </wx-form-item>

      <wx-form-item :label="t('panel.redirect')" :help="t('panel.redirect-help')">
        <wx-input v-model="redirect" placeholder="/thank-you" />
      </wx-form-item>
    </wx-card>
  </div>
</template>

<style scoped>
.wx-inbox-general {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}
</style>
