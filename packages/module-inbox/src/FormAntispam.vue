<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxAlert, WxCard, WxFormItem, WxInputNumber, WxSelect, WxSwitch, WxText } from '@webx-ui/core'
import { option } from './options'
import type { CaptchaProvider, CaptchaSite, FormOptions } from './types'

/**
 * The four layers in front of the door (§7), as the three anybody would want to change.
 *
 * The origin check is not here: it is not a dial, it is a fact about where the site is served
 * from, and it lives in the configuration with the rest of the deployment. The captcha keys
 * are not here either — they are the site's, one pair for every form, and a secret repeated
 * in each form's settings is a secret scattered over rows (§5).
 *
 * What is here is what the site has, under the select: a reCAPTCHA key of the wrong kind is
 * the widget's "Invalid key type" and nothing else, and the select alone gives an editor no
 * way of knowing which kind the site expects.
 */
const options = defineModel<FormOptions>({ required: true })

const props = defineProps<{
  /** From the form as the server sent it; absent on a server older than this. */
  site?: Record<CaptchaProvider, CaptchaSite>
}>()

const t = useTranslate('webx-inbox')

const honeypot = option<boolean>(options, 'antispam.honeypot', true)
const seconds = option<number>(options, 'antispam.min_seconds', 3)
const throttle = option<number>(options, 'antispam.throttle', 5)
const captcha = option<string>(options, 'antispam.captcha', 'off')

const captchas = computed(() => [
  { value: 'off', label: t('panel.captcha-off') },
  { value: 'recaptcha', label: t('panel.captcha-recaptcha') },
  { value: 'turnstile', label: t('panel.captcha-turnstile') },
])

/** The provider chosen and what the site has for it, or null for none. */
const chosen = computed(() => {
  const provider = captcha.value

  if (provider !== 'recaptcha' && provider !== 'turnstile') return null

  return { provider, site: props.site?.[provider] ?? null }
})

const kind = computed(() => {
  const site = chosen.value?.site

  return site ? t(`panel.captcha-${chosen.value!.provider}-${site.type}`) : null
})
</script>

<template>
  <wx-card>
    <wx-form-item :help="t('panel.honeypot-help')">
      <wx-switch v-model="honeypot" :label="t('panel.honeypot')" />
    </wx-form-item>

    <wx-form-item :label="t('panel.min-seconds')" :help="t('panel.min-seconds-help')">
      <wx-input-number v-model="seconds" :min="0" :max="120" />
    </wx-form-item>

    <wx-form-item :label="t('panel.throttle')" :help="t('panel.throttle-help')">
      <wx-input-number v-model="throttle" :min="0" :max="120" />
    </wx-form-item>

    <wx-form-item :label="t('panel.captcha')" :help="t('panel.captcha-keys')">
      <wx-select v-model="captcha" :options="captchas" />
    </wx-form-item>

    <wx-text v-if="kind" size="sm" tone="muted">{{ kind }}</wx-text>

    <wx-alert
      v-if="chosen?.site && !chosen.site.configured"
      type="warning"
      variant="soft"
      :closable="false"
      :description="t('panel.captcha-unconfigured')"
    />
  </wx-card>
</template>
