<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  HttpError,
  tellSitePage,
  useAdmin,
  useErrorText,
  useTranslate,
  type SiteBarContext,
} from '@webx-ui/module-admin'
import { toast, type LocalizedValue } from '@webx-ui/core'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import { ruleInput, seoOf } from './rule'
import type { SeoOrigin, SeoSiteBarField, SeoSiteBarReport, SeoValue } from './types'

/**
 * The SEO tab of the panel on the site (WEBX_UI_SITE_BAR.md, §7).
 *
 * Built around the question it is opened for — «why does this page have that title» — so every
 * field shows what the page prints and where that came from, next to the input. Four fields
 * only: the title, the description, the H1 and whether search engines may index the page. The
 * rest of the card (sharing, canonical, structured data) is a link into the panel, where there
 * is room for it.
 *
 * Where a save goes is the server's call (`target`): the record's own card when it has one and
 * the editor may change it, a rule for this exact address otherwise. A field that a source
 * above that place already sets is flagged before saving — an edit there would change nothing a
 * visitor sees, and finding that out afterwards is the bug report this tab exists to prevent.
 */
const props = defineProps<{ context: SiteBarContext }>()

const admin = useAdmin()
useSeoMessages()
const t = useTranslate('webx-seo')
const message = useErrorText()
const api = createSeoApi(admin)

const TEXT_FIELDS = ['title', 'description', 'h1'] as const
/* Soft limits, the same the card counts with: long is not wrong, it is only cut short. */
const LIMITS = { title: 60, description: 160 }

const report = ref<SeoSiteBarReport | null>(null)
const loading = ref(true)
const failed = ref(false)
const saving = ref(false)
const form = reactive({ title: '', description: '', h1: '', noindex: false })

const base = computed(() => admin.basePath.replace(/\/$/, ''))
const seoPath = computed(() => admin.modules?.find((module) => module.id === 'seo')?.path ?? '/seo')
const mayOpenRules = computed(() => admin.can('seo.view') || admin.can('seo.manage'))

function ruleHref(id: number): string {
  return `${base.value}${seoPath.value}?rule=${id}`
}

/* The place in the panel with every field — the record's form, or the rule. */
const allFieldsHref = computed(() => {
  const found = report.value

  if (found === null) return null

  if (found.target === 'entity') {
    return props.context.entity?.edit ? `${base.value}${props.context.entity.edit}` : null
  }

  return found.rule?.exact ? ruleHref(found.rule.id) : `${base.value}${seoPath.value}`
})

/* The source a save writes to, as the chain names it: an empty input falls through to the
   source below it, which the tab cannot see, so its preview shows nothing rather than a guess. */
const targetSource = computed<SeoOrigin>(() =>
  report.value?.target === 'entity' ? 'entity' : 'rule',
)

const notice = computed(() => {
  const found = report.value

  if (found === null) return ''
  if (found.target === 'entity') return t('site-bar.into-entity')
  if (found.rule?.exact) return t('site-bar.into-rule')

  return t('site-bar.into-new-rule')
})

function directives(robots: string | null | undefined): string[] {
  return (robots ?? '')
    .split(',')
    .map((one) => one.trim())
    .filter((one) => one !== '')
}

/* `noindex` switched on or off, and every other directive somebody wrote kept as written. */
function robotsWith(robots: string | null | undefined, noindex: boolean): string | null {
  const kept = directives(robots).filter((one) => one !== 'noindex')
  const next = noindex ? ['noindex', ...kept] : kept

  return next.length === 0 ? null : next.join(', ')
}

function fill(found: SeoSiteBarReport): void {
  form.title = found.fields.title.own ?? ''
  form.description = found.fields.description.own ?? ''
  form.h1 = found.fields.h1.own ?? ''
  form.noindex = directives(found.fields.robots.own).includes('noindex')
}

async function load(): Promise<SeoSiteBarReport | null> {
  loading.value = report.value === null
  failed.value = false

  try {
    const found = await api.siteBar(props.context.url, props.context.locale)

    report.value = found
    fill(found)

    return found
  } catch {
    failed.value = true

    return null
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.context.url, props.context.locale],
  () => void load(),
)

onMounted(() => void load())

function origin(source: SeoOrigin | null): string {
  if (source === null) return t('site-bar.empty')
  if (source === 'rule')
    return t('site-bar.from-rule', { pattern: report.value?.rule?.pattern ?? '' })

  return t(`site-bar.from-${source}`)
}

function overriddenText(field: SeoSiteBarField): string | null {
  const over = report.value?.fields[field].overridden

  if (!over) return null

  return over === 'rule'
    ? t('site-bar.overridden-rule', { pattern: report.value?.rule?.pattern ?? '' })
    : t('site-bar.overridden-other')
}

function count(text: string): number {
  return text.trim().length
}

/* What the search result will roughly look like after the save. */
const preview = computed(() => {
  const fields = report.value?.fields
  const line = (field: 'title' | 'description', typed: string): string => {
    const one = fields?.[field]

    if (!one) return typed
    if (one.overridden) return one.value ?? ''
    if (typed.trim() !== '') return typed.trim()

    return one.source !== targetSource.value ? (one.value ?? '') : ''
  }

  return {
    title: line('title', form.title),
    description: line('description', form.description),
  }
})

/* One language of a language map: set it, or take it out when emptied. */
function inLanguage(map: LocalizedValue | undefined, locale: string, text: string): LocalizedValue {
  const next: Record<string, string> = typeof map === 'object' && map !== null ? { ...map } : {}

  if (text.trim() === '') {
    delete next[locale]
  } else {
    next[locale] = text.trim()
  }

  return next
}

async function saveIntoEntity(found: SeoSiteBarReport): Promise<void> {
  const seo: Record<string, unknown> = {
    robots: robotsWith(found.fields.robots.own, form.noindex),
  }

  // Only this language: the others, and the rest of the card, stay as they are on the server.
  for (const field of TEXT_FIELDS) {
    seo[field] = { [found.locale]: form[field].trim() === '' ? null : form[field].trim() }
  }

  await api.saveEntitySeo(found.entity!.type, found.entity!.id, seo as SeoValue)
}

async function saveIntoRule(found: SeoSiteBarReport): Promise<void> {
  /* The whole rule goes back (`PUT` replaces it), so it is read first. A mask is read too: the
     rule written for this address starts as its copy, or the page would lose whatever the mask
     gave it the moment its own rule took the mask's place. */
  const current = found.rule ? await api.url(found.rule.id) : null
  const value = seoOf(current)

  for (const field of TEXT_FIELDS) {
    value[field] = inLanguage(value[field], found.locale, form[field])
  }

  value.robots = robotsWith(value.robots, form.noindex)

  if (current && found.rule?.exact) {
    await api.updateUrl(
      current.id,
      ruleInput(
        {
          match_type: current.match_type,
          pattern: current.pattern,
          priority: current.priority,
          is_active: current.is_active,
        },
        value,
      ),
    )

    return
  }

  await api.createUrl(
    ruleInput({ match_type: 'exact', pattern: found.url, priority: 0, is_active: true }, value),
  )
}

async function save(): Promise<void> {
  const found = report.value

  if (found === null || saving.value) return

  saving.value = true

  try {
    if (found.target === 'entity' && found.entity) {
      await saveIntoEntity(found)
    } else {
      await saveIntoRule(found)
    }

    toast.success(t('site-bar.saved'))

    const fresh = await load()

    // The browser's tab shows the new title at once; the rest of `<head>` on the next load.
    if (fresh?.title) tellSitePage({ type: 'title', title: fresh.title })
  } catch (error) {
    toast.danger(
      error instanceof HttpError && error.status === 422 ? t('site-bar.failed') : message(error),
    )
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="wx-seo-bar">
    <wx-loading v-if="loading" />

    <wx-result v-else-if="failed || report === null" status="info" :title="t('site-bar.error')">
      <wx-button @click="load">{{ t('site-bar.retry') }}</wx-button>
    </wx-result>

    <form v-else class="wx-seo-bar__form" @submit.prevent="save">
      <wx-alert
        v-if="report.target === 'rule' && report.rule && !report.rule.exact"
        type="info"
        :title="t('site-bar.replaces-mask', { pattern: report.rule.pattern })"
      />

      <div v-for="field in TEXT_FIELDS" :key="field" class="wx-seo-bar__field">
        <wx-form-item :label="t(`site-bar.${field}`)">
          <wx-textarea v-if="field === 'description'" v-model="form.description" :rows="3" />
          <wx-input v-else v-model="form[field]" />
        </wx-form-item>

        <div class="wx-seo-bar__meta">
          <wx-text size="sm" tone="muted" class="wx-seo-bar__now">
            {{ t('site-bar.now') }}:
            <wx-badge size="sm" :type="report.fields[field].overridden ? 'warning' : 'default'">
              {{ origin(report.fields[field].source) }}
            </wx-badge>
          </wx-text>
          <!-- What the page prints, where it is not simply what the input holds: a value from
               another source, or one the title template wrapped. -->
          <wx-text
            v-if="report.fields[field].value && report.fields[field].value !== form[field].trim()"
            size="sm"
            class="wx-seo-bar__value"
          >
            {{ report.fields[field].value }}
          </wx-text>
          <wx-text
            v-if="field !== 'h1'"
            size="sm"
            :tone="count(form[field]) > LIMITS[field] ? 'warning' : 'muted'"
          >
            {{ count(form[field]) }} / {{ LIMITS[field] }}
          </wx-text>
        </div>

        <wx-alert
          v-if="overriddenText(field)"
          type="warning"
          class="wx-seo-bar__over"
          :title="overriddenText(field)!"
        >
          <template
            v-if="report.fields[field].overridden === 'rule' && report.rule && mayOpenRules"
            #actions
          >
            <wx-link :href="ruleHref(report.rule.id)" target="_top" size="sm">
              {{ t('site-bar.open-rule') }}
            </wx-link>
          </template>
        </wx-alert>
      </div>

      <div class="wx-seo-bar__field">
        <wx-checkbox v-model="form.noindex" :label="t('site-bar.noindex')" />
        <div class="wx-seo-bar__meta">
          <wx-text size="sm" tone="muted" class="wx-seo-bar__now">
            {{ t('site-bar.now') }}:
            <wx-badge size="sm" :type="report.fields.robots.overridden ? 'warning' : 'default'">
              {{ report.fields.robots.value ?? t('site-bar.empty') }}
            </wx-badge>
          </wx-text>
        </div>
        <wx-alert
          v-if="overriddenText('robots')"
          type="warning"
          class="wx-seo-bar__over"
          :title="overriddenText('robots')!"
        />
      </div>

      <div class="wx-seo-bar__preview">
        <wx-text size="sm" tone="muted">{{ t('site-bar.preview') }}</wx-text>
        <div class="wx-seo-bar__snippet">
          <div class="wx-seo-bar__snippet-address">{{ report.url }}</div>
          <div class="wx-seo-bar__snippet-title">{{ preview.title }}</div>
          <div class="wx-seo-bar__snippet-text">{{ preview.description }}</div>
        </div>
      </div>

      <wx-text size="sm" tone="muted">{{ notice }}</wx-text>

      <div class="wx-seo-bar__actions">
        <wx-button type="primary" native-type="submit" :loading="saving">
          {{ t('site-bar.save') }}
        </wx-button>
        <wx-link v-if="allFieldsHref" :href="allFieldsHref" target="_top" size="sm" external>
          {{ t('site-bar.all-fields') }}
        </wx-link>
      </div>
    </form>
  </div>
</template>

<style scoped>
/*
 * Drawer-wide: 380px beside a page on a desktop, the whole width on a phone. One column, the
 * way a narrow form reads; nothing here is wide enough to want two.
 */
.wx-seo-bar__form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-seo-bar__field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-seo-bar__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-seo-bar__now {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-seo-bar__value {
  flex-basis: 100%;
  overflow-wrap: anywhere;
  order: 3;
}

.wx-seo-bar__over {
  margin-block-start: var(--wx-space-4);
}

.wx-seo-bar__preview {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

/* The card's snippet, at the drawer's width: a reminder of how much of a line survives. */
.wx-seo-bar__snippet {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  padding: var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

.wx-seo-bar__snippet-address {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

.wx-seo-bar__snippet-title {
  color: var(--wx-color-primary);
  font-size: var(--wx-font-size-lg);
  overflow-wrap: anywhere;
}

.wx-seo-bar__snippet-text {
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

.wx-seo-bar__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
}
</style>
