<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate, WxScreenHead } from '@webx-ui/module-admin'
import { toast, WxBadge, WxEmpty, WxIcon, WxSkeleton, WxText } from '@webx-ui/core'
import { createRegionsApi } from './api'
import { useBlocksMessages } from './i18n'
import type { RegionRow } from './types'

/**
 * The layout regions of the site: one card each.
 *
 * Not a table. A site declares two regions, the header and the footer, and a table of two rows
 * is a heading row and a lot of empty width — while what a reader wants from each is a sentence:
 * is the site showing the blocks or the code, and which view of the code.
 */
defineOptions({ name: 'WxRegionsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/regions' })

const context = useAdmin()
const api = createRegionsApi(context)
useBlocksMessages()

const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const regions = ref<RegionRow[] | null>(null)

/**
 * The state as one chip, and a second one only when it adds something: a region on the site with
 * edits waiting is two facts, and folding them into one word would hide either.
 */
function chips(region: RegionRow): { label: string; type: 'success' | 'warning' | 'default' }[] {
  if (region.published) {
    const live = { label: t('region.state-live'), type: 'success' as const }

    return region.has_draft ? [live, { label: t('region.state-edits'), type: 'warning' }] : [live]
  }

  // Never saved, or taken off the site: either way the site prints the code's view. A draft
  // that exists is said beside it, because it is what the next "Publish" would put there.
  const fallback = { label: t('region.state-fallback'), type: 'default' as const }

  return region.has_draft
    ? [fallback, { label: t('region.state-draft'), type: 'warning' }]
    : [fallback]
}

onMounted(async () => {
  try {
    regions.value = await api.list()
  } catch (error) {
    regions.value = []
    toast.danger(message(error))
  }
})
</script>

<template>
  <div class="wx-regions">
    <wx-screen-head :title="t('region.title')" :subtitle="t('region.intro')" />

    <div v-if="regions === null" class="wx-regions__grid">
      <div v-for="index in 2" :key="index" class="wx-regions__card">
        <wx-skeleton title :rows="2" />
      </div>
    </div>

    <wx-empty
      v-else-if="regions.length === 0"
      :title="t('region.empty')"
      :description="t('region.empty-help')"
    />

    <div v-else class="wx-regions__grid">
      <router-link
        v-for="region in regions"
        :key="region.name"
        :to="`${props.base}/${encodeURIComponent(region.name)}`"
        class="wx-regions__card is-link"
      >
        <div class="wx-regions__top">
          <span class="wx-regions__title">{{ region.title }}</span>
          <code class="wx-regions__name">{{ region.name }}</code>
        </div>

        <wx-text v-if="region.description" size="sm" tone="muted" class="wx-regions__description">
          {{ region.description }}
        </wx-text>

        <div class="wx-regions__chips">
          <wx-badge v-for="chip in chips(region)" :key="chip.label" :type="chip.type" dot>
            {{ chip.label }}
          </wx-badge>
        </div>

        <div class="wx-regions__meta">
          <wx-text size="sm" tone="muted">{{ t('region.count', { count: region.count }) }}</wx-text>
          <wx-text size="sm" tone="muted">
            {{
              region.fallback
                ? t('region.fallback-view', { view: region.fallback })
                : t('region.fallback-none')
            }}
          </wx-text>
          <wx-date v-if="region.updated_at" :value="region.updated_at" size="sm" tone="muted" />
        </div>

        <wx-icon name="chevron-right" class="wx-regions__go" aria-hidden="true" />
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.wx-regions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

/* Side by side while each card keeps room for its sentence; one above the other on a phone. */
.wx-regions__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--wx-gap, var(--wx-space-16));
}

.wx-regions__card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-16);
  padding-inline-end: var(--wx-space-40);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-card);
  color: var(--wx-text-default);
  text-decoration: none;
}

.wx-regions__card.is-link {
  transition: border-color var(--wx-duration-fast);
}

.wx-regions__card.is-link:hover {
  border-color: var(--wx-border-strong);
}

.wx-regions__card.is-link:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-regions__top {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-8);
}

.wx-regions__title {
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
  color: var(--wx-text-strong);
}

.wx-regions__name {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-regions__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}

.wx-regions__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4) var(--wx-space-12);
  /* A view name is one long word; without this it pushes the card wider than its column. */
  overflow-wrap: anywhere;
}

.wx-regions__go {
  position: absolute;
  inset-block-start: 50%;
  inset-inline-end: var(--wx-space-12);
  translate: 0 -50%;
  color: var(--wx-text-muted);
}
</style>
