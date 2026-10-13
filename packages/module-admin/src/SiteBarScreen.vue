<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import type { BadgeType } from '@webx-ui/core'
import { useAdmin } from './admin'
import DateText from './DateText.vue'
import { HttpError } from './http'
import { useTranslate } from './i18n'
import {
  fetchSiteBarContext,
  tellSitePage,
  type SiteBarContext,
  type SiteBarStatus,
  type SiteBarTab,
} from './siteBar'

/**
 * `/cms/_bar` — the panel on the site, as the drawer beside a page of the site shows it
 * (WEBX_UI_SITE_BAR.md, §5).
 *
 * The same panel as everywhere else, in a shell without its navigation: the page it is about is
 * the one beside it, and the way into the rest of the panel is «Open in panel», which leaves the
 * site for the record's own screen. The page is named by `?url=`, which the script on the site
 * writes from its own address, and its language by `?locale=`, from its `<html lang>`.
 *
 * «Page» is the frame's own tab; the others come from the modules (`siteBarTabs`), each drawn
 * only where the server listed it for this page.
 */
const admin = useAdmin()
const route = useRoute()
const t = useTranslate('webx-admin')

const context = ref<SiteBarContext | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const tab = ref<string | number>('page')

const url = computed(() => (typeof route.query.url === 'string' ? route.query.url : '/'))
const locale = computed(() =>
  typeof route.query.locale === 'string' && route.query.locale !== '' ? route.query.locale : null,
)

/* The modules' tabs the server offered here, in its order. One it offered that no module on this
   side draws is left out rather than shown empty. */
const moduleTabs = computed<SiteBarTab[]>(() => {
  const known = (admin.modules ?? []).flatMap((module) => module.siteBarTabs ?? [])

  return (context.value?.sections ?? []).flatMap((name) => {
    const tab = name === 'page' ? undefined : known.find((one) => one.name === name)

    return tab ? [tab] : []
  })
})
const entity = computed(() => context.value?.entity ?? null)

/*
 * Out of the frame to the panel's own address. The whole window rather than the frame: a
 * second panel inside the drawer is a panel nobody can work in.
 */
const panelHref = computed(() =>
  entity.value?.edit ? `${admin.basePath.replace(/\/$/, '')}${entity.value.edit}` : null,
)

const STATUS_TYPES: Record<SiteBarStatus, BadgeType> = {
  published: 'success',
  scheduled: 'info',
  unpublished: 'warning',
  trashed: 'danger',
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const body = await fetchSiteBarContext(admin.http, admin.apiPath, url.value, locale.value)

    context.value = body.data
    tab.value = body.data.sections.includes('page')
      ? 'page'
      : (moduleTabs.value[0]?.name ?? body.data.sections[0] ?? 'page')
  } catch (caught) {
    context.value = null
    // A refusal is the server's to word — «nothing here you may edit»; anything else is ours.
    error.value =
      caught instanceof HttpError && caught.status === 403 && caught.message !== ''
        ? caught.message
        : t('site-bar.error')
  } finally {
    loading.value = false
  }
}

watch(url, () => void load())

function close(): void {
  tellSitePage('close')
}

// Esc inside the frame never reaches the page's own listener, so it is passed on.
function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && !event.defaultPrevented) close()
}

onMounted(() => {
  void load()
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="wx-site-bar">
    <header class="wx-site-bar__head">
      <div class="wx-site-bar__what">
        <wx-text weight="semibold" truncate class="wx-site-bar__title">
          <template v-if="entity">{{ entity.kind }} · {{ entity.label }}</template>
          <template v-else>{{ t('site-bar.title') }}</template>
        </wx-text>

        <div v-if="entity" class="wx-site-bar__state">
          <wx-badge :type="STATUS_TYPES[entity.status]" dot size="sm">
            {{ t(`site-bar.status-${entity.status}`) }}
          </wx-badge>
          <wx-text v-if="entity.updatedAt" size="sm" tone="muted">
            {{ t('site-bar.edited') }} <DateText :value="entity.updatedAt" size="sm" tone="muted" />
          </wx-text>
        </div>
      </div>

      <wx-action
        v-if="panelHref"
        icon="external-link"
        :title="t('site-bar.open-in-panel')"
        :href="panelHref"
        target="_top"
      />
      <wx-action icon="close" :title="t('site-bar.close')" @click="close" />
    </header>

    <wx-alert
      v-if="entity?.draft?.differs"
      type="warning"
      class="wx-site-bar__draft"
      :title="
        entity.draft.by ? t('site-bar.draft-by', { name: entity.draft.by }) : t('site-bar.draft')
      "
    />

    <div v-if="loading" class="wx-site-bar__body">
      <wx-loading />
    </div>

    <div v-else-if="error !== null" class="wx-site-bar__body">
      <wx-result status="info" :title="error">
        <wx-button @click="load">{{ t('site-bar.retry') }}</wx-button>
      </wx-result>
    </div>

    <wx-tabs v-else-if="context" v-model="tab" size="sm" class="wx-site-bar__tabs">
      <wx-tab v-if="context.sections.includes('page')" value="page" :label="t('site-bar.tab-page')">
        <div class="wx-site-bar__body">
          <wx-descriptions v-if="entity" :columns="1" size="sm">
            <wx-descriptions-item :label="t('site-bar.address')">
              <wx-text size="sm" class="wx-site-bar__url">{{ context.url }}</wx-text>
            </wx-descriptions-item>
            <wx-descriptions-item :label="t('site-bar.status')">
              {{ t(`site-bar.status-${entity.status}`) }}
            </wx-descriptions-item>
          </wx-descriptions>

          <wx-text v-else size="sm" tone="muted">{{ t('site-bar.no-entity') }}</wx-text>

          <wx-button
            v-if="panelHref"
            type="primary"
            block
            :href="panelHref"
            target="_top"
            class="wx-site-bar__open"
          >
            {{ t('site-bar.open-in-panel') }}
          </wx-button>
        </div>
      </wx-tab>

      <wx-tab v-for="one in moduleTabs" :key="one.name" :value="one.name" :label="one.label(admin)">
        <div class="wx-site-bar__body">
          <component :is="one.component" :context="context" />
        </div>
      </wx-tab>
    </wx-tabs>
  </div>
</template>

<style scoped>
/*
 * The whole of the frame, drawer-wide: 380px on a desktop, a phone's width on a phone. Spaced
 * like a dialog rather than like a screen of the panel — there is no sidebar here to line up
 * with.
 */
.wx-site-bar {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
}

.wx-site-bar__head {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-4);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8) var(--wx-space-16);
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-site-bar__what {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-site-bar__state {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-site-bar__draft {
  margin: var(--wx-space-12) var(--wx-space-16) 0;
}

.wx-site-bar__tabs {
  padding: var(--wx-space-8) var(--wx-space-16) 0;
}

.wx-site-bar__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  padding: var(--wx-space-16) 0;
}

.wx-site-bar > .wx-site-bar__body {
  padding: var(--wx-space-16);
}

.wx-site-bar__url {
  word-break: break-all;
}
</style>
