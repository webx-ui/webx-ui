<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxButton, WxLink, WxSkeleton, WxText } from '@webx-ui/core'
import AuditDetails from './AuditDetails.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditIssue, AuditIssueQuery } from './types'

/**
 * The addresses of one check, under its row in the findings table: loaded when the row opens,
 * fifty at a time.
 */
const props = defineProps<{ run: number; query: AuditIssueQuery }>()

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()

const issues = ref<AuditIssue[] | null>(null)
const more = ref(false)
const page = ref(1)
const failure = ref<string | null>(null)

async function load(next = 1): Promise<void> {
  try {
    const answer = await api.issues(props.run, { ...props.query, page: next, per_page: 50 })

    issues.value = next === 1 ? answer.data : [...(issues.value ?? []), ...answer.data]
    page.value = answer.current_page
    more.value = answer.current_page < answer.last_page
  } catch (error) {
    failure.value = message(error)
  }
}

onMounted(() => load())
</script>

<template>
  <div class="wx-audit-issues">
    <wx-text v-if="failure" size="sm" tone="danger">{{ failure }}</wx-text>
    <wx-skeleton v-else-if="!issues" :rows="2" />

    <template v-else>
      <div v-for="issue in issues" :key="issue.id" class="wx-audit-issues__item">
        <div class="wx-audit-issues__head">
          <wx-link
            v-if="issue.url"
            :href="issue.url"
            target="_blank"
            class="wx-audit-issues__url"
            >{{ issue.url }}</wx-link
          >
          <wx-badge v-if="issue.state === 'new'" type="primary" size="sm">{{
            t('page.state-new')
          }}</wx-badge>
        </div>
        <audit-details :details="issue.details" />
      </div>

      <wx-button v-if="more" size="sm" variant="text" @click="load(page + 1)">…</wx-button>
    </template>
  </div>
</template>

<style scoped>
.wx-audit-issues {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-audit-issues__item {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-audit-issues__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-audit-issues__url {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
