<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxCard, WxFormItem, WxInputNumber, WxSelect, WxSwitch } from '@webx-ui/core'
import { option } from './options'
import type { FormOptions } from './types'

/**
 * The four layers in front of the door (§7), as the three anybody would want to change.
 *
 * The origin check is not here: it is not a dial, it is a fact about where the site is served
 * from, and it lives in the configuration with the rest of the deployment. The captcha keys
 * are not here either — they are the site's, one pair for every form, and a secret repeated
 * in each form's settings is a secret scattered over rows (§5).
 */
const options = defineModel<FormOptions>({ required: true })

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
</script>

<template>
  <wx-card>
    <wx-form-item :label="t('panel.honeypot')" :help="t('panel.honeypot-help')">
      <wx-switch v-model="honeypot" />
    </wx-form-item>

    <wx-form-item :label="t('panel.min-seconds')" :help="t('panel.min-seconds-help')">
      <wx-input-number v-model="seconds" :min="0" :max="120" />
    </wx-form-item>

    <wx-form-item :label="t('panel.throttle')" :help="t('panel.throttle-help')">
      <wx-input-number v-model="throttle" :min="0" :max="120" />
    </wx-form-item>

    <wx-form-item :label="t('panel.captcha')" :help="t('panel.captcha-help')">
      <wx-select v-model="captcha" :options="captchas" />
    </wx-form-item>
  </wx-card>
</template>
