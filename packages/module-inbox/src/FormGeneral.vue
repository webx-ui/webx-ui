<script setup lang="ts">
import { computed } from 'vue'
import {
  emptyLink,
  useTranslate,
  WxCodeField,
  WxLinkPicker,
  type LinkValue,
} from '@webx-ui/module-admin'
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

const description = option<LocalizedValue>(options, 'description', {})
const heading = option<LocalizedValue>(options, 'thank-you.heading', {})
const text = option<LocalizedValue>(options, 'thank-you.text', {})
const submit = option<LocalizedValue>(options, 'design.submit-text', {})
/*
 * Where the visitor goes instead of the thank-you: a link like a menu item's — a record of the
 * site, which the server opens in the visitor's language, or an address. Nothing chosen is
 * "Nowhere", the thank-you in place of the form. A plain string is what a form saved before the
 * choice holds, and it is an address.
 */
const stored = option<LinkValue | string | null>(options, 'redirect', null)

/*
 * The button under the thank-you in a dialog (FORM_STEPS §16.2): its words — «Close» when empty —
 * and where it leads; nowhere chosen, it closes the dialog.
 */
const buttonText = option<LocalizedValue>(options, 'thank-you.button', {})
const buttonStored = option<LinkValue | null>(options, 'thank-you.button-link', null)
const buttonLink = computed<LinkValue | null>({
  get: (): LinkValue => buttonStored.value ?? { ...emptyLink(), target: 'none' },
  set: (value: LinkValue | null) => {
    buttonStored.value = value === null || value.target === 'none' ? null : value
  },
})

/* «Next» held until the step is answered (§3.6), and the dialog scrolled as a whole (§16.1). */
const holdNext = option<boolean>(options, 'steps.hold-next', false)
const dialogScroll = option<string | null>(options, 'dialog.scroll', null)
const dialogPage = computed<boolean>({
  get: () => dialogScroll.value === 'page',
  set: (on: boolean) => {
    dialogScroll.value = on ? 'page' : null
  },
})

const redirect = computed<LinkValue | null>({
  get: (): LinkValue => {
    const value = stored.value

    if (typeof value === 'string') return { ...emptyLink(), target: 'url', url: value }

    return value ?? { ...emptyLink(), target: 'none' }
  },
  set: (value: LinkValue | null) => {
    stored.value = value === null || value.target === 'none' ? null : value
  },
})
</script>

<template>
  <div class="wx-inbox-general">
    <wx-card>
      <wx-form-item :label="t('panel.title')" :error="errors.title?.[0]" required>
        <wx-input v-model="settings.title" localized />
      </wx-form-item>

      <wx-form-item :label="t('panel.description')" :help="t('panel.description-help')">
        <wx-input v-model="description" localized />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.slug')"
        :help="t('panel.slug-help')"
        :error="errors.slug?.[0]"
        required
      >
        <wx-code-field v-model="settings.slug" :maxlength="96" placeholder="contact" />
      </wx-form-item>

      <wx-form-item :help="t('panel.is-enabled-help')">
        <wx-switch v-model="settings.is_enabled" :label="t('panel.is-enabled')" />
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

      <wx-form-item
        :label="t('panel.redirect')"
        :help="t('panel.redirect-help')"
        :error="errors['options.redirect']?.[0]"
      >
        <!-- A redirect is not clicked: a new tab and rel mean nothing for it. -->
        <wx-link-picker v-model="redirect" :attributes="false" />
      </wx-form-item>

      <wx-form-item :label="t('panel.thank-you-button')" :help="t('panel.thank-you-button-help')">
        <wx-input v-model="buttonText" localized />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.thank-you-button-link')"
        :help="t('panel.thank-you-button-link-help')"
        :error="errors['options.thank-you.button-link']?.[0]"
      >
        <wx-link-picker v-model="buttonLink" :attributes="false" />
      </wx-form-item>
    </wx-card>

    <wx-card :title="t('panel.on-the-site')">
      <wx-form-item :help="t('panel.hold-next-help')">
        <wx-switch v-model="holdNext" :label="t('panel.hold-next')" />
      </wx-form-item>

      <wx-form-item :help="t('panel.dialog-page-help')">
        <wx-switch v-model="dialogPage" :label="t('panel.dialog-page')" />
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
