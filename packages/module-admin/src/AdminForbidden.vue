<script setup lang="ts">
import { useRouter } from 'vue-router'
import { WxButton, WxResult } from '@webx-ui/core'
import { useTranslate } from './i18n'

/**
 * What a section this administrator may not open shows, in place of its page.
 *
 * The menu already leaves such a section out; this is for the address typed by hand, the old
 * bookmark, the link a colleague sent. Without it the page would draw itself and fill with the
 * server's refusals, one per request — which reads as a broken panel, not as a closed door.
 */
const router = useRouter()
const t = useTranslate('webx-admin')
</script>

<template>
  <wx-result
    class="wx-admin-forbidden"
    status="403"
    :title="t('shell.forbidden-title')"
    :subtitle="t('shell.forbidden-description')"
  >
    <template #actions>
      <wx-button type="primary" @click="router.push('/')">{{
        t('shell.not-found-home')
      }}</wx-button>
    </template>
  </wx-result>
</template>
