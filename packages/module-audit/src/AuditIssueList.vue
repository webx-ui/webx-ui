<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { toast, WxBadge, WxButton, WxSegmented, WxSkeleton, WxText, WxTooltip } from '@webx-ui/core'
import AuditAddress from './AuditAddress.vue'
import AuditDetails from './AuditDetails.vue'
import AuditFixDialog from './AuditFixDialog.vue'
import AuditHideDialog from './AuditHideDialog.vue'
import AuditLinkGroups from './AuditLinkGroups.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditIgnoreRule, AuditIssue, AuditIssueQuery } from './types'

/**
 * The addresses of one check, under its row in the findings table: loaded when the row opens,
 * fifty at a time. A check that has fixes gets a button on each address; the dialog shows what
 * it would change before anything does. «Hide» asks for the reason; on the list of hidden ones
 * each says why, and «Show again» removes the rule.
 *
 * Each finding is a card: the address with its count, the table under it. A check of links that
 * redirect can also be read by link — every page loaded, one card per link with its pages.
 */
const props = defineProps<{
  run: number
  query: AuditIssueQuery
  fixable?: boolean
  /** The check's title, for the hiding dialog. */
  title?: string
}>()

const emit = defineEmits<{ changed: [] }>()

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
const hiding = ref<AuditIssue | null>(null)
const hideDialog = ref(false)

type View = 'pages' | 'links'

const view = ref<View>('pages')
const everything = ref<AuditIssue[] | null>(null)

/** Rows with where each address leads: the findings can be grouped by link. */
const byLink = computed(() =>
  (issues.value?.[0]?.details.table?.columns ?? []).some((column) => column.key === 'location'),
)

const views = computed(() => [
  { value: 'pages', label: t('page.view-pages') },
  { value: 'links', label: t('page.view-links') },
])

/* By link needs every page of the check, not the fifty loaded: a link is counted on all of them. */
async function loadEverything(): Promise<void> {
  if (everything.value) return

  try {
    const all: AuditIssue[] = []
    let next = 1
    let last = 1

    do {
      const answer = await api.issues(props.run, { ...props.query, page: next, per_page: 200 })

      all.push(...answer.data)
      last = answer.last_page
      next++
    } while (next <= last)

    everything.value = all
  } catch (error) {
    failure.value = message(error)
  }
}

function switchView(value: View): void {
  view.value = value
  if (value === 'links') loadEverything()
}

function hide(issue: AuditIssue): void {
  hiding.value = issue
  hideDialog.value = true
}

/* What the rule hid leaves this list at once; the table above counts again. */
function hidden(rule: AuditIgnoreRule): void {
  if (issues.value && !rule.pattern) issues.value = []
  else if (issues.value && hiding.value)
    issues.value = issues.value.filter((one) => one.id !== hiding.value?.id)

  emit('changed')
}

async function unhide(issue: AuditIssue): Promise<void> {
  if (!issue.ignore) return

  try {
    await api.unhide(issue.ignore.id)
    toast.success(t('page.unhidden'))
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  }
}

function why(issue: AuditIssue): string {
  const rule = issue.ignore

  if (!rule) return ''

  return rule.created_by
    ? t('page.hidden-by', { who: rule.created_by, reason: rule.reason })
    : t('page.hidden-reason', { reason: rule.reason })
}

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
      <wx-segmented
        v-if="byLink"
        :model-value="view"
        :options="views"
        size="sm"
        class="wx-audit-issues__view"
        @update:model-value="switchView($event as View)"
      />

      <template v-if="view === 'links'">
        <audit-link-groups v-if="everything" :issues="everything" />
        <wx-skeleton v-else :rows="2" />
      </template>

      <template v-else>
        <div v-for="issue in issues" :key="issue.id" class="wx-audit-issues__item">
          <div class="wx-audit-issues__head">
            <audit-address v-if="issue.url" :href="issue.url" strong class="wx-audit-issues__url" />
            <wx-badge v-if="issue.state === 'new'" type="primary" size="sm">{{
              t('page.state-new')
            }}</wx-badge>
            <wx-tooltip
              v-if="issue.details.count != null && issue.details.table"
              :content="issue.details.summary ?? undefined"
              :disabled="!issue.details.summary"
            >
              <wx-badge round size="sm" class="wx-audit-issues__count">{{
                issue.details.count
              }}</wx-badge>
            </wx-tooltip>
            <wx-badge v-if="issue.fixed_with" type="success" size="sm">{{
              t('page.fix-pressed')
            }}</wx-badge>
            <wx-button
              v-else-if="fixable && can && !issue.ignored"
              size="sm"
              variant="outline"
              icon="check"
              class="wx-audit-issues__fix"
              @click="fix(issue)"
              >{{ t('page.fix-button') }}</wx-button
            >
            <wx-button
              v-if="can && issue.ignored && issue.ignore"
              size="sm"
              variant="text"
              icon="eye"
              class="wx-audit-issues__end"
              @click="unhide(issue)"
              >{{ t('page.unhide') }}</wx-button
            >
            <wx-button
              v-else-if="can && !issue.ignored"
              size="sm"
              variant="text"
              icon="eye-off"
              :class="{ 'wx-audit-issues__end': !fixable || issue.fixed_with }"
              @click="hide(issue)"
              >{{ t('page.hide') }}</wx-button
            >
          </div>
          <div class="wx-audit-issues__body">
            <wx-text v-if="issue.ignore" size="sm" tone="muted">{{ why(issue) }}</wx-text>
            <audit-details
              :details="issue.details"
              :counted="issue.details.count != null && !!issue.details.table"
            />
          </div>
        </div>

        <wx-button v-if="more" size="sm" variant="text" @click="load(page + 1)">…</wx-button>
      </template>
    </template>

    <audit-fix-dialog
      v-if="fixing"
      v-model:open="dialog"
      :run="run"
      :issue="fixing"
      @fixed="fixed"
    />

    <audit-hide-dialog
      v-if="hiding"
      v-model:open="hideDialog"
      :issue="hiding"
      :title="props.title ?? hiding.check"
      @hidden="hidden"
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

.wx-audit-issues__view {
  align-self: flex-start;
}

/* A card per finding: the page is the edge, its addresses are inside. */
.wx-audit-issues__item {
  display: flex;
  flex-direction: column;
  min-width: 0;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-audit-issues__head {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-muted);
  background: var(--wx-bg-subtle);
}

.wx-audit-issues__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
}

.wx-audit-issues__body:empty {
  display: none;
}

.wx-audit-issues__fix,
.wx-audit-issues__end {
  margin-inline-start: auto;
}

/* The address gives way to the badges and buttons beside it, one line at any width. */
.wx-audit-issues__url {
  flex: 0 1 auto;
}

.wx-audit-issues__count {
  flex-shrink: 0;
}
</style>
