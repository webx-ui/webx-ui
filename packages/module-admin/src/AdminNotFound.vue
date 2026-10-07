<script setup lang="ts">
import { useRouter } from 'vue-router'
import { WxButton, WxResult } from '@webx-ui/core'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'

/**
 * What an address no section answers for shows.
 *
 * Until now nothing did, and the content area simply stayed empty — an old bookmark, a section
 * this site does not have installed, or a typo all looked like a panel that had broken. The way
 * back is the panel's start, which is wherever `/` leads on this site.
 *
 * Drawn only once the panel is ready: while the manifest is on its way the shell shows its own
 * spinner, and a section that does exist is never «not found» for the second before it loads.
 */
const admin = useAdmin()
const router = useRouter()
const t = useTranslate('webx-admin')
</script>

<template>
  <wx-result
    v-if="admin.state.status === 'ready'"
    class="wx-admin-not-found"
    status="404"
    :title="t('shell.not-found-title')"
    :subtitle="t('shell.not-found-description')"
  >
    <template #actions>
      <wx-button type="primary" @click="router.push('/')">{{
        t('shell.not-found-home')
      }}</wx-button>
    </template>
  </wx-result>
</template>
