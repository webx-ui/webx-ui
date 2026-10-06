<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxButton, WxSkeleton, WxText } from '@webx-ui/core'
import AuditAddress from './AuditAddress.vue'
import { createAuditApi } from './api'
import { statusType } from './addresses'
import { useAuditMessages } from './i18n'
import type { AuditHostTarget, AuditHosts } from './types'

/**
 * Where one host stands, under its row on «Outgoing»: the addresses on it the pages point at, each
 * a card with the pages under it — a font or a profile link sits in the layout and is on every
 * page, so by page it would be the same line forty times — and the fields of the database that
 * hold it, with «Open in the editor». Fifty of each, broken links first; a card counts all its
 * pages and lists fifty.
 */
const props = defineProps<{ host: string }>()

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()
const router = useRouter()

const places = ref<AuditHosts | null>(null)
const failure = ref<string | null>(null)

/** What the row's «Broken» counts: an error, or no answer at all (0). */
const broken = (status: number | null | undefined) =>
  status !== null && status !== undefined && (status >= 400 || status === 0)

/** How many pages a card lists before «Show all». */
const FEW = 5

const open = ref(new Set<string>())

function keyOf(target: AuditHostTarget): string {
  return `${target.kind} ${target.url}`
}

function shown(target: AuditHostTarget) {
  return open.value.has(keyOf(target)) ? target.places : target.places.slice(0, FEW)
}

/* «Show all» turns into «Collapse», so a long card folds back where it is read. */
function toggle(target: AuditHostTarget): void {
  const next = new Set(open.value)

  if (!next.delete(keyOf(target))) next.add(keyOf(target))
  open.value = next
}

onMounted(async () => {
  try {
    places.value = await api.hosts({ host: props.host })
  } catch (error) {
    failure.value = message(error)
  }
})
</script>

<template>
  <div class="wx-audit-places">
    <wx-text v-if="failure" size="sm" tone="danger">{{ failure }}</wx-text>
    <wx-skeleton v-else-if="!places" :rows="2" />

    <template v-else>
      <section v-if="places.targets?.length" class="wx-audit-places__group">
        <wx-text size="sm" weight="semibold">{{ t('page.host-pages') }}</wx-text>

        <div v-for="target in places.targets" :key="keyOf(target)" class="wx-audit-places__card">
          <div class="wx-audit-places__head">
            <wx-badge type="default" size="sm" class="wx-audit-places__fixed">{{
              target.kind
            }}</wx-badge>
            <audit-address :href="target.url" strong />
            <wx-badge
              v-if="broken(target.status)"
              :type="statusType(target.status)"
              size="sm"
              class="wx-audit-places__fixed"
              >{{ target.status || t('page.status-none') }}</wx-badge
            >
            <wx-text size="sm" tone="muted" class="wx-audit-places__count">{{
              t('page.link-pages', { count: target.pages })
            }}</wx-text>
          </div>

          <ul class="wx-audit-places__pages">
            <li v-for="(place, index) in shown(target)" :key="index">
              <audit-address :href="place.page" />
              <wx-text v-if="place.anchor" size="sm" tone="muted" class="wx-audit-places__anchor"
                >«{{ place.anchor }}»</wx-text
              >
            </li>
          </ul>

          <wx-button
            v-if="target.places.length > FEW"
            size="sm"
            variant="text"
            class="wx-audit-places__more"
            @click="toggle(target)"
            >{{
              open.has(keyOf(target)) ? t('page.link-collapse') : t('page.link-show-all')
            }}</wx-button
          >
        </div>
      </section>

      <section v-if="places.fields?.length" class="wx-audit-places__group">
        <wx-text size="sm" weight="semibold">{{ t('page.host-fields') }}</wx-text>

        <div class="wx-audit-places__card">
          <div v-for="(field, index) in places.fields" :key="index" class="wx-audit-places__field">
            <div class="wx-audit-places__record">
              <wx-text size="sm">{{ field.record_label }}</wx-text>
              <wx-text size="sm" tone="muted"
                >{{ field.source }} · {{ field.field
                }}{{ field.locale ? ` · ${field.locale}` : '' }}</wx-text
              >
              <wx-badge v-if="!field.published" size="sm">{{ t('page.unpublished') }}</wx-badge>
            </div>
            <audit-address :href="field.url" />
            <wx-button
              v-if="field.edit_url"
              size="sm"
              variant="text"
              icon="edit"
              class="wx-audit-places__fixed"
              @click="router.push(field.edit_url)"
              >{{ t('page.open-editor') }}</wx-button
            >
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.wx-audit-places {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-audit-places__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-audit-places__card {
  min-width: 0;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-audit-places__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-muted);
  background: var(--wx-bg-subtle);
  font-size: var(--wx-font-size-sm);
}

.wx-audit-places__fixed {
  flex-shrink: 0;
}

.wx-audit-places__count {
  flex-shrink: 0;
  margin-inline-start: auto;
  white-space: nowrap;
}

.wx-audit-places__pages {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  list-style: none;
  font-size: var(--wx-font-size-sm);
}

.wx-audit-places__pages li {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-audit-places__anchor {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-audit-places__more {
  margin: 0 var(--wx-space-12) var(--wx-space-8);
}

/* One grid for every field: the record, where its address is, the editor. */
.wx-audit-places__field {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 3fr) auto;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-6) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-audit-places__field:last-child {
  border-bottom: 0;
}

.wx-audit-places__record {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}
</style>
