<script setup lang="ts">
import { computed, ref } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxButton, WxIcon, WxText } from '@webx-ui/core'
import AuditAddress from './AuditAddress.vue'
import AuditLinkAttrs from './AuditLinkAttrs.vue'
import { differences } from './addresses'
import AuditStatus from './AuditStatus.vue'
import { useAuditMessages } from './i18n'
import type { AuditIssue } from './types'

/**
 * The findings of a redirect check turned inside out: one card per link, the pages it is on
 * under it. A link in the footer or the menu is on every page and is fixed once, in one place —
 * by page, it would be the same row forty times.
 */
const props = defineProps<{ issues: AuditIssue[] }>()

useAuditMessages()

const t = useTranslate('webx-audit')

interface LinkGroup {
  url: string
  location: string
  status: number | null
  pages: LinkPlace[]
  /** The pages write the link with different `rel` or `target`: each says its own. */
  mixed: boolean
}

interface LinkPlace {
  page: string
  rel: string | null
  target: string | null
}

/** How many pages a card lists before «Show all». */
const FEW = 5

const open = ref(new Set<string>())

const groups = computed<LinkGroup[]>(() => {
  const found = new Map<string, LinkGroup>()

  for (const issue of props.issues) {
    for (const row of issue.details.table?.rows ?? []) {
      const url = String(row.url ?? '')
      const location = String(row.location ?? '')
      const key = `${url}\n${location}`
      const group = found.get(key) ?? { url, location, status: null, pages: [], mixed: false }
      const place = { page: issue.url ?? '', rel: attr(row.rel), target: attr(row.target) }
      const first = group.pages[0]

      group.status ??= typeof row.status === 'number' ? row.status : null
      if (first && (first.rel !== place.rel || first.target !== place.target)) group.mixed = true
      if (place.page && !group.pages.some((one) => one.page === place.page)) group.pages.push(place)
      found.set(key, group)
    }
  }

  return [...found.values()].sort((a, b) => b.pages.length - a.pages.length)
})

function attr(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

function keyOf(group: LinkGroup): string {
  return `${group.url}\n${group.location}`
}

function shown(group: LinkGroup): LinkPlace[] {
  return open.value.has(keyOf(group)) ? group.pages : group.pages.slice(0, FEW)
}

/* «Show all» turns into «Collapse», so a long card folds back where it is read. */
function toggle(group: LinkGroup): void {
  const next = new Set(open.value)

  if (!next.delete(keyOf(group))) next.add(keyOf(group))
  open.value = next
}
</script>

<template>
  <div class="wx-audit-links">
    <div v-for="group in groups" :key="keyOf(group)" class="wx-audit-links__card">
      <div class="wx-audit-links__head">
        <audit-address :href="group.url" type="muted" class="wx-audit-links__from" />
        <wx-icon
          name="arrow-right"
          size="1em"
          class="wx-audit-links__arrow"
          :label="t('page.leads-to')"
        />
        <audit-address :href="group.location" strong class="wx-audit-links__to" />
        <wx-badge
          v-for="change in differences(group.url, group.location)"
          :key="change"
          type="default"
          size="sm"
          >{{ t(`page.change-${change}`) }}</wx-badge
        >
        <audit-link-attrs
          v-if="!group.mixed && group.pages[0]"
          :rel="group.pages[0].rel"
          :target="group.pages[0].target"
          class="wx-audit-links__fixed"
        />
        <audit-status v-if="group.status !== null" :code="group.status" />
        <wx-text size="sm" tone="muted" class="wx-audit-links__count">{{
          t('page.link-pages', { count: group.pages.length })
        }}</wx-text>
      </div>

      <ul class="wx-audit-links__pages">
        <li v-for="place in shown(group)" :key="place.page">
          <audit-address :href="place.page" />
          <audit-link-attrs
            v-if="group.mixed"
            :rel="place.rel"
            :target="place.target"
            class="wx-audit-links__fixed"
          />
        </li>
      </ul>

      <wx-button
        v-if="group.pages.length > FEW"
        size="sm"
        variant="text"
        class="wx-audit-links__more"
        @click="toggle(group)"
        >{{ open.has(keyOf(group)) ? t('page.link-collapse') : t('page.link-show-all') }}</wx-button
      >
    </div>
  </div>
</template>

<style scoped>
.wx-audit-links {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-audit-links__card {
  min-width: 0;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-audit-links__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-muted);
  background: var(--wx-bg-subtle);
  font-size: var(--wx-font-size-sm);
}

.wx-audit-links__from,
.wx-audit-links__to {
  flex: 0 1 auto;
}

.wx-audit-links__arrow {
  flex-shrink: 0;
  color: var(--wx-text-muted);
}

.wx-audit-links__fixed {
  flex-shrink: 0;
}

.wx-audit-links__pages li {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-audit-links__count {
  flex-shrink: 0;
  margin-inline-start: auto;
  white-space: nowrap;
}

.wx-audit-links__pages {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  list-style: none;
  font-size: var(--wx-font-size-sm);
}

.wx-audit-links__more {
  margin: 0 var(--wx-space-12) var(--wx-space-8);
}
</style>
