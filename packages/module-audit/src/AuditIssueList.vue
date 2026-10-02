<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxButton, WxLink, WxSkeleton, WxText } from '@webx-ui/core'
import AuditDetails from './AuditDetails.vue'
import AuditFixDialog from './AuditFixDialog.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditIssue, AuditIssueQuery } from './types'

/**
 * The addresses of one check, under its row in the findings table: loaded when the row opens,
 * fifty at a time. A check that has fixes gets a button on each address; the dialog shows what
 * it would change before anything does.
 */
const props = defineProps<{ run: number; query: AuditIssueQuery; fixable?: boolean }>()

const context = useAdmin()
const api = createAuditApi(context)
// A boolean, not a computed: the permissions do not change while the screen is open.
const can = context.can('audit.manage')
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()

const issues = ref<AuditIssue[] | null>(null)
const more = ref(false)
const page = ref(1)
const failure = ref<string | null>(null)
const fixing = ref<AuditIssue | null>(null)
const dialog = ref(false)

function fix(issue: AuditIssue): void {
  fixing.value = issue
  dialog.value = true
}

function fixed(id: string): void {
  if (fixing.value) fixing.value.fixed_with = id
}

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
          <wx-badge v-if="issue.fixed_with" type="success" size="sm">{{
            t('page.fix-pressed')
          }}</wx-badge>
          <wx-button
            v-else-if="fixable && can"
            size="sm"
            variant="outline"
            icon="check"
            class="wx-audit-issues__fix"
            @click="fix(issue)"
            >{{ t('page.fix-button') }}</wx-button
          >
        </div>
        <audit-details :details="issue.details" />
      </div>

      <wx-button v-if="more" size="sm" variant="text" @click="load(page + 1)">…</wx-button>
    </template>

    <audit-fix-dialog
      v-if="fixing"
      v-model:open="dialog"
      :run="run"
      :issue="fixing"
      @fixed="fixed"
    />
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

.wx-audit-issues__fix {
  margin-inline-start: auto;
}

.wx-audit-issues__url {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
