<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxButton, WxLink, WxSegmented, WxSkeleton, WxText } from '@webx-ui/core'
import AuditDetails from './AuditDetails.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditComparisonKind, AuditComparisonRow, AuditIssue } from './types'

/**
 * One check of a comparison opened: its new, fixed or persisting findings, fifty at a time —
 * fixed ones as the earlier run stored them, the others from the later run.
 */
const props = defineProps<{ from: number; to: number; row: AuditComparisonRow }>()

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()

const KINDS: AuditComparisonKind[] = ['new', 'fixed', 'persisting']

/* Open on what changed: new first, then fixed — persisting only when nothing else is there. */
const kind = ref<AuditComparisonKind>(KINDS.find((one) => props.row[one] > 0) ?? 'new')
const issues = ref<AuditIssue[] | null>(null)
const page = ref(1)
const more = ref(false)
const failure = ref<string | null>(null)

const options = computed(() =>
  KINDS.map((value) => ({
    value,
    label: `${t(`page.kind-${value}`)} · ${props.row[value]}`,
    disabled: props.row[value] === 0,
  })),
)

async function load(next = 1): Promise<void> {
  failure.value = null

  try {
    const answer = await api.compareIssues({
      from: props.from,
      to: props.to,
      kind: kind.value,
      check: props.row.check,
      page: next,
      per_page: 50,
    })

    issues.value = next === 1 ? answer.data : [...(issues.value ?? []), ...answer.data]
    page.value = answer.current_page
    more.value = answer.current_page < answer.last_page
  } catch (error) {
    failure.value = message(error)
  }
}

watch(
  kind,
  () => {
    issues.value = null
    void load()
  },
  { immediate: true },
)
</script>

<template>
  <div class="wx-audit-compare">
    <wx-segmented v-model="kind" :options="options" size="sm" :aria-label="t('page.compare')" />

    <wx-text v-if="failure" size="sm" tone="danger">{{ failure }}</wx-text>
    <wx-skeleton v-else-if="!issues" :rows="2" />

    <template v-else>
      <div v-for="issue in issues" :key="issue.id" class="wx-audit-compare__item">
        <wx-link v-if="issue.url" :href="issue.url" target="_blank" class="wx-audit-compare__url">{{
          issue.url
        }}</wx-link>
        <audit-details :details="issue.details" />
      </div>

      <wx-button v-if="more" size="sm" variant="text" @click="load(page + 1)">…</wx-button>
    </template>
  </div>
</template>

<style scoped>
.wx-audit-compare {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-audit-compare__item {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-audit-compare__url {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
