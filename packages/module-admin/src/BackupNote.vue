<script setup lang="ts">
import { computed } from 'vue'
import { WxAlert, WxText } from '@webx-ui/core'
import { useAdmin } from './admin'
import { useDates } from './dates'
import { useTranslate } from './i18n'
import DateText from './DateText.vue'

defineOptions({ name: 'WxBackupNote' })

/**
 * One line about the nightly database dump (§6 of the backups spec).
 *
 * A backup whose breakage is discovered on the day it was needed is not a backup, and the
 * cheapest guard against that is a sentence somewhere a person already looks. This is that
 * sentence: no screen, no buttons, nothing to click. When the newest file is older than two
 * days — or there is no file at all — the same line turns into a warning, because by then the
 * only useful thing to say is "go and look at the cron".
 *
 * It renders nothing at all for a site that has switched the dump off, and nothing for an
 * administrator without `settings.view`: whoever may see the system section may see this, and
 * for everybody else it is a fact about the server that is none of their business.
 */
const STALE_AFTER_HOURS = 48

const admin = useAdmin()
const dates = useDates()
const t = useTranslate('webx-admin')

const backup = computed(() => admin.state.manifest?.backup ?? null)
const visible = computed(() => backup.value !== null && admin.can('settings.view'))

const stale = computed(() => {
  const at = backup.value?.at

  if (at === null || at === undefined) return true

  const taken = new Date(at).getTime()

  return Number.isNaN(taken) || Date.now() - taken > STALE_AFTER_HOURS * 3600_000
})

/*
 * "4.2 MB" in English, "4,2 МБ" in Russian, and the right abbreviation in the seven others —
 * from `Intl` rather than from three words in every language file, which is what a unit of
 * measure is for.
 */
const size = computed(() => {
  const bytes = backup.value?.bytes

  if (bytes === null || bytes === undefined) return ''

  const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte'] as const
  let value = bytes
  let unit = 0

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }

  return new Intl.NumberFormat(admin.i18n.state.locale, {
    style: 'unit',
    unit: units[unit],
    unitDisplay: 'short',
    maximumFractionDigits: value >= 10 || unit === 0 ? 0 : 1,
  }).format(value)
})

/* The warning names the day rather than saying "two days ago": the reader is about to go and
   look at a log, and a date is what they will be looking for in it. */
const warning = computed(() =>
  backup.value?.at == null
    ? t('backup.never')
    : t('backup.stale', { date: dates.short(backup.value.at) }),
)
</script>

<template>
  <wx-alert v-if="visible && stale" type="warning" variant="soft" :description="warning" />

  <p v-else-if="visible" class="wx-backup-note">
    <wx-text size="sm" tone="muted">{{ t('backup.title') }}</wx-text>
    <date-text :value="backup?.at" />
    <wx-text v-if="size" size="sm" tone="muted">· {{ size }}</wx-text>
  </p>
</template>

<style scoped>
/*
 * A footnote, and shaped like one: it wraps rather than pushes, because the three parts of it
 * are three separate elements and a narrow panel has to be allowed to break between them.
 */
.wx-backup-note {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-4) var(--wx-space-6);
  margin: 0;
}
</style>
