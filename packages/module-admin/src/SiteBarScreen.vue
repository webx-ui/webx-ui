<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { confirm, toast, type BadgeType } from '@webx-ui/core'
import { useAdmin } from './admin'
import DateText from './DateText.vue'
import { useErrorText } from './errors'
import { HttpError } from './http'
import { useTranslate } from './i18n'
import {
  discardSiteBarEntity,
  fetchSiteBarContext,
  publishSiteBarEntity,
  siteBarKey,
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
const errorText = useErrorText()

const context = ref<SiteBarContext | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const tab = ref<string | number>('page')
/* Set while the bar itself moves the tab — on reading the page — rather than the editor. */
let settling = false

const url = computed(() => (typeof route.query.url === 'string' ? route.query.url : '/'))
const locale = computed(() =>
  typeof route.query.locale === 'string' && route.query.locale !== '' ? route.query.locale : null,
)

/* The modules' tabs the server offered here, in its order. One it offered that no module on this
   side draws is left out rather than shown empty; a tab that stands for several sections is
   drawn once, where the first of them stands. */
const moduleTabs = computed<SiteBarTab[]>(() => {
  const known = (admin.modules ?? []).flatMap((module) => module.siteBarTabs ?? [])
  const drawn: SiteBarTab[] = []

  for (const name of context.value?.sections ?? []) {
    const tab = name === 'page' ? undefined : known.find((one) => namesOf(one).includes(name))

    if (tab && !drawn.includes(tab)) drawn.push(tab)
  }

  return drawn
})

function namesOf(tab: SiteBarTab): string[] {
  return [tab.name, ...(tab.sections ?? [])]
}

/* The tabs on screen by the names they answer to: «Page», then the modules'. */
const tabNames = computed(() => [
  ...(context.value?.sections.includes('page') ? ['page'] : []),
  ...moduleTabs.value.map((one) => one.name),
])

/* The tab the page remembered from the last time — the frame is made again on every page. */
const remembered = computed(() =>
  typeof route.query.tab === 'string' && route.query.tab !== '' ? route.query.tab : null,
)
const entity = computed(() => context.value?.entity ?? null)

/*
 * Out of the frame to the panel's own address, in a tab of its own: a second panel inside the
 * drawer is a panel nobody can work in, and taking the whole window would close the page the
 * editor came from — the one they are about to check the edit on.
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

/**
 * @param quiet Read again behind what is on screen — after an edit in a tab — rather than from
 *              scratch: the tabs stay, and the one that is open stays open.
 */
async function load(quiet = false): Promise<void> {
  loading.value = !quiet
  error.value = null

  try {
    const body = await fetchSiteBarContext(admin.http, admin.apiPath, url.value, locale.value)

    context.value = body.data

    if (!quiet || !tabNames.value.includes(String(tab.value))) {
      const chosen =
        remembered.value !== null && tabNames.value.includes(remembered.value)
          ? remembered.value
          : (tabNames.value[0] ?? 'page')

      // Not a choice of the editor's, so not one for the page to remember.
      if (chosen !== tab.value) settling = true
      tab.value = chosen
    }
  } catch (caught) {
    if (quiet && context.value !== null) return

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

// The page keeps the tab the editor chose in its `wx-bar` state (§5): the next page's frame opens
// on it. A tab the bar fell back on — the remembered one is not on this page — is not a choice,
// and the page keeps the one it had.
watch(tab, (name) => {
  if (settling) {
    settling = false

    return
  }

  tellSitePage({ type: 'tab', name: String(name) })
})

provide(siteBarKey, {
  show: (name: string) => {
    const found =
      name === 'page' ? 'page' : moduleTabs.value.find((one) => namesOf(one).includes(name))?.name

    if (found && tabNames.value.includes(found)) tab.value = found
  },
  refresh: () => load(true),
})

/*
 * The draft's buttons are the record's (§9): there when its section said how it is published and
 * the administrator may, whoever left the draft. Without them the bar says the draft is there and
 * the way into the panel stays in the head.
 */
const draft = computed(() => (entity.value?.draft?.differs ? entity.value.draft : null))
const mayPublish = computed(() => draft.value !== null && entity.value?.can.publish === true)
const busy = ref(false)

async function publish(): Promise<void> {
  const found = entity.value

  if (!found || busy.value) return

  busy.value = true

  try {
    await publishSiteBarEntity(admin.http, admin.apiPath, found)
    // The page beside the bar is the site again, and shows what was just published.
    tellSitePage({ type: 'reload' })
  } catch (caught) {
    toast.danger(errorText(caught))
    // A 409 is somebody's edit the bar had not seen: the head counts it now.
    void load(true)
  } finally {
    busy.value = false
  }
}

async function discard(): Promise<void> {
  const found = entity.value

  if (!found || busy.value) return

  const agreed = await confirm({
    title: t('site-bar.discard-title'),
    message: t('site-bar.discard-text'),
    confirmText: t('site-bar.discard'),
    cancelText: t('site-bar.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  busy.value = true

  try {
    await discardSiteBarEntity(admin.http, admin.apiPath, found)
    tellSitePage({ type: 'reload' })
  } catch (caught) {
    toast.danger(errorText(caught))
  } finally {
    busy.value = false
  }
}

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
        target="_blank"
        rel="noopener"
      />
      <wx-action icon="close" :title="t('site-bar.close')" @click="close" />
    </header>

    <wx-alert
      v-if="draft && mayPublish"
      type="warning"
      class="wx-site-bar__draft"
      :title="t('site-bar.changes', { count: draft.changes ?? 1 })"
      :description="
        draft.mine === false && draft.by ? t('site-bar.draft-other', { name: draft.by }) : undefined
      "
    >
      <template #actions>
        <wx-button size="sm" type="primary" :loading="busy" @click="publish">
          {{ t('site-bar.publish') }}
        </wx-button>
        <wx-button size="sm" variant="outline" :disabled="busy" @click="discard">
          {{ t('site-bar.discard') }}
        </wx-button>
        <!-- After a reload the page is the site again: the whole draft is in its preview. -->
        <wx-button
          v-if="entity?.preview"
          size="sm"
          variant="text"
          :href="entity.preview"
          target="_blank"
          rel="noopener"
        >
          {{ t('site-bar.preview') }}
        </wx-button>
      </template>
    </wx-alert>

    <wx-alert
      v-else-if="draft"
      type="warning"
      class="wx-site-bar__draft"
      :title="draft.by ? t('site-bar.draft-by', { name: draft.by }) : t('site-bar.draft')"
    >
      <template v-if="entity?.preview" #actions>
        <wx-button size="sm" variant="text" :href="entity.preview" target="_blank" rel="noopener">
          {{ t('site-bar.preview') }}
        </wx-button>
      </template>
    </wx-alert>

    <div v-if="loading" class="wx-site-bar__body">
      <wx-loading />
    </div>

    <div v-else-if="error !== null" class="wx-site-bar__body">
      <wx-result status="info" :title="error">
        <wx-button @click="load">{{ t('site-bar.retry') }}</wx-button>
      </wx-result>
    </div>

    <!-- Kept alive: a half-typed form survives a look at another tab, and a block picked on the
         page reaches «Blocks» even while «Page» is in front. -->
    <wx-tabs v-else-if="context" v-model="tab" size="sm" keep-alive class="wx-site-bar__tabs">
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
            target="_blank"
            rel="noopener"
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
