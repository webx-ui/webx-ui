<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import { toast, WxBadge, WxButton, WxLink, WxSkeleton, WxText } from '@webx-ui/core'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { SeoSitemapStatus } from './types'

/**
 * The sitemap, above the rules (§17.6 of the SEO spec): where it is, how many addresses each
 * file holds, when it was built — and how many visible pages the resolver kept out of it and
 * why. That last line is the reason the card exists: "why is my page not in Google" starts with
 * a page that closed itself, and nobody looks at robots in a card they did not open.
 *
 * The map rebuilds itself on every save; the button is for what changes without one — a date
 * that has come — and for somebody who wants to see the numbers move.
 */
const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')
const message = useErrorText()

const status = ref<SeoSitemapStatus | null>(null)
const rebuilding = ref(false)

const canManage = context.can('seo.manage')

onMounted(async () => {
  try {
    status.value = await api.sitemap()
  } catch {
    // Said by the table under it, which asks the same server; a card that fails twice is noise.
  }
})

async function rebuild(): Promise<void> {
  rebuilding.value = true

  try {
    status.value = await api.rebuildSitemap()
    toast.success(t('page.sitemap-rebuilt'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    rebuilding.value = false
  }
}
</script>

<template>
  <section class="wx-seo-sitemap" :aria-label="t('page.sitemap')">
    <div v-if="!status" class="wx-seo-sitemap__main"><wx-skeleton :rows="2" /></div>

    <wx-text v-else-if="!status.enabled" size="sm" tone="muted">{{
      t('page.sitemap-off')
    }}</wx-text>

    <template v-else>
      <div class="wx-seo-sitemap__main">
        <div class="wx-seo-sitemap__head">
          <wx-text size="sm" weight="semibold">{{ t('page.sitemap') }}</wx-text>
          <wx-link :href="status.url" target="_blank" class="wx-seo-sitemap__url">
            {{ status.url }}
          </wx-link>
        </div>

        <div class="wx-seo-sitemap__facts">
          <template v-if="status.total > 0">
            <wx-text size="sm">{{ t('page.sitemap-total', { count: status.total }) }}</wx-text>
            <wx-badge v-for="(count, file) in status.files" :key="file">
              {{ file }} · {{ count }}
            </wx-badge>
          </template>
          <wx-text v-else size="sm" tone="muted">{{ t('page.sitemap-empty') }}</wx-text>

          <wx-text v-if="status.built_at" size="sm" tone="muted">
            {{ t('page.sitemap-built') }} <wx-date :value="status.built_at" />
          </wx-text>
        </div>

        <!-- Counts at the end of the line: plurals are not something the panel's words do. -->
        <wx-text
          v-if="status.excluded.noindex + status.excluded.canonical > 0"
          size="sm"
          tone="warning"
        >
          {{
            t('page.sitemap-excluded', {
              noindex: status.excluded.noindex,
              canonical: status.excluded.canonical,
            })
          }}
        </wx-text>
      </div>

      <wx-button
        v-if="canManage"
        size="sm"
        icon="refresh"
        :loading="rebuilding"
        class="wx-seo-sitemap__rebuild"
        @click="rebuild"
      >
        {{ t('page.sitemap-rebuild') }}
      </wx-button>
    </template>
  </section>
</template>

<style scoped>
.wx-seo-sitemap {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wx-space-12);
  padding: var(--wx-space-12) var(--wx-space-16);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-seo-sitemap__main {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-seo-sitemap__head,
.wx-seo-sitemap__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6) var(--wx-space-12);
  min-width: 0;
}

/* A long domain breaks rather than pushing the card sideways on a phone. */
.wx-seo-sitemap__url {
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-seo-sitemap__rebuild {
  flex: none;
}
</style>
