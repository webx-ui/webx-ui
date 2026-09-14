<script setup lang="ts">
import { computed, ref, type Component } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import {
  localizedValue,
  useLocales,
  WxCheckboxGroup,
  WxCodeEditor,
  WxFormItem,
  WxInput,
  WxTabs,
  WxTab,
  WxText,
  WxTextarea,
  type LocalizedValue,
} from '@webx-ui/core'
import { useSeoMessages } from './i18n'
import { robotsDirectives, type SeoImage, type SeoValue } from './types'

/**
 * What a page says about itself, as a field.
 *
 * Registered as `wx-seo`, so it can be dropped into any screen by a patch, and used directly by
 * the rule form in this package. The value is one object — the same shape the server stores —
 * rather than a dozen loose keys, because SEO is edited and reasoned about as a unit.
 *
 * The text fields are language maps and say so: the same chip every other localized field in the
 * panel grows, moving together with them.
 */
defineOptions({ name: 'WxSeo' })

const props = withDefaults(
  defineProps<{
    /**
     * The field that picks the share image — `WxMediaField` in a panel that has the library.
     *
     * Handed in rather than imported: this package does not depend on the media one, and a panel
     * without a library still edits everything else about a page.
     */
    mediaField?: Component
    disabled?: boolean
    /**
     * Soft limits, for the counters only. Long is not wrong — search engines shorten what they
     * shorten — so nothing here refuses a longer line, it only says how long it is.
     */
    titleLimit?: number
    descriptionLimit?: number
    /** What the snippet preview shows above the title. */
    site?: string
    /** Hidden when a screen has no use for them. */
    structuredData?: boolean
  }>(),
  {
    mediaField: undefined,
    disabled: false,
    titleLimit: 60,
    descriptionLimit: 160,
    site: undefined,
    structuredData: true,
  },
)

const value = defineModel<SeoValue | null>({ default: null })

useSeoMessages()

const t = useTranslate('webx-seo')
const locales = useLocales()

const jsonLdText = ref<string | null>(null)
const jsonLdBroken = ref(false)

/** One field of the value, without rebuilding the object in every handler. */
function field<K extends keyof SeoValue>(key: K) {
  return computed({
    get: () => value.value?.[key],
    set: (next: SeoValue[K]) => {
      value.value = { ...(value.value ?? {}), [key]: next }
    },
  })
}

const title = field('title')
const h1 = field('h1')
const description = field('description')
const keywords = field('keywords')
const ogTitle = field('og_title')
const ogDescription = field('og_description')

const canonical = computed<string>({
  get: () => value.value?.canonical ?? '',
  set: (next: string) => {
    value.value = { ...(value.value ?? {}), canonical: next.trim() === '' ? null : next }
  },
})

const image = computed({
  get: () => value.value?.og_image ?? null,
  set: (next: SeoImage | null) => {
    value.value = { ...(value.value ?? {}), og_image: next }
  },
})

/* The directives the checkboxes cover. Anything else somebody wrote — `max-snippet:20` and its
   kind — is kept and shown, because silently dropping a line a person typed is worse than not
   offering a box for it. */
const directives = computed(() => (value.value?.robots ?? '').split(',').map((one) => one.trim()))

const kept = computed(() =>
  directives.value.filter((one) => one !== '' && !robotsDirectives.includes(one as never)),
)

const known = computed({
  get: () => directives.value.filter((one) => robotsDirectives.includes(one as never)),
  set: (next: readonly string[]) => {
    const merged = [...next, ...kept.value].join(', ')
    value.value = { ...(value.value ?? {}), robots: merged === '' ? null : merged }
  },
})

const robotsOptions = computed(() =>
  robotsDirectives.map((one) => ({ value: one, label: t(`card.${one}`) })),
)

const jsonLd = computed<string>({
  get: () => {
    if (jsonLdText.value !== null) return jsonLdText.value

    const stored = value.value?.json_ld

    return stored === null || stored === undefined ? '' : JSON.stringify(stored, null, 2)
  },
  set: (next: string) => {
    /* The text is kept as typed while it is being typed: reformatting somebody's JSON the
       moment it happens to parse moves the caret out from under them. */
    jsonLdText.value = next

    if (next.trim() === '') {
      jsonLdBroken.value = false
      value.value = { ...(value.value ?? {}), json_ld: null }

      return
    }

    try {
      value.value = { ...(value.value ?? {}), json_ld: JSON.parse(next) }
      jsonLdBroken.value = false
    } catch {
      jsonLdBroken.value = true
    }
  },
})

/** What the counters and the preview read: the language being edited. */
function inLocale(map: LocalizedValue | undefined): string {
  return localizedValue(map, locales.active.value)
}

const counted = computed(() => ({
  title: inLocale(title.value as LocalizedValue | undefined).length,
  description: inLocale(description.value as LocalizedValue | undefined).length,
}))

const preview = computed(() => ({
  title: inLocale(title.value as LocalizedValue | undefined),
  description: inLocale(description.value as LocalizedValue | undefined),
  address: canonical.value || (props.site ?? ''),
}))

const hasPreview = computed(() => preview.value.title !== '' || preview.value.description !== '')

const keptHelp = computed(() =>
  kept.value.length === 0
    ? undefined
    : t('card.robots-kept', { directives: kept.value.join(', ') }),
)

function tone(count: number, limit: number): 'muted' | 'warning' {
  return count > limit ? 'warning' : 'muted'
}
</script>

<template>
  <div class="wx-seo">
    <wx-tabs keep-alive>
      <wx-tab :label="t('card.section-page')" value="page">
        <div class="wx-seo__fields">
          <wx-form-item :label="t('card.title')" :disabled="disabled">
            <wx-input v-model="title" localized />
            <div class="wx-seo__count">
              <wx-text size="sm" :tone="tone(counted.title, titleLimit)">
                {{ counted.title }} / {{ titleLimit }}
              </wx-text>
            </div>
          </wx-form-item>

          <wx-form-item :label="t('card.h1')" :disabled="disabled">
            <wx-input v-model="h1" localized />
          </wx-form-item>

          <wx-form-item :label="t('card.description')" :disabled="disabled">
            <wx-textarea v-model="description" localized :rows="3" />
            <div class="wx-seo__count">
              <wx-text size="sm" :tone="tone(counted.description, descriptionLimit)">
                {{ counted.description }} / {{ descriptionLimit }}
              </wx-text>
            </div>
          </wx-form-item>

          <wx-form-item :label="t('card.keywords')" :disabled="disabled">
            <wx-input v-model="keywords" localized />
          </wx-form-item>

          <div v-if="hasPreview" class="wx-seo__preview">
            <wx-text size="sm" tone="muted">{{ t('card.preview') }}</wx-text>
            <div class="wx-seo__snippet">
              <div v-if="preview.address" class="wx-seo__snippet-address">
                {{ preview.address }}
              </div>
              <div class="wx-seo__snippet-title">{{ preview.title }}</div>
              <div class="wx-seo__snippet-text">{{ preview.description }}</div>
            </div>
          </div>
        </div>
      </wx-tab>

      <wx-tab :label="t('card.section-share')" value="share">
        <div class="wx-seo__fields">
          <component
            :is="mediaField"
            v-if="mediaField"
            v-model="image"
            :label="t('card.og-image')"
            aspect="16/9"
            accept="image"
            :disabled="disabled"
          />

          <wx-form-item :label="t('card.og-title')" :help="t('card.og-help')" :disabled="disabled">
            <wx-input v-model="ogTitle" localized />
          </wx-form-item>

          <wx-form-item :label="t('card.og-description')" :disabled="disabled">
            <wx-textarea v-model="ogDescription" localized :rows="2" />
          </wx-form-item>
        </div>
      </wx-tab>

      <wx-tab :label="t('card.section-advanced')" value="advanced">
        <div class="wx-seo__fields">
          <wx-form-item
            :label="t('card.canonical')"
            :help="t('card.canonical-help')"
            :disabled="disabled"
          >
            <wx-input v-model="canonical" placeholder="https://example.com/page" />
          </wx-form-item>

          <wx-form-item :label="t('card.robots')" :help="keptHelp" :disabled="disabled">
            <wx-checkbox-group v-model="known" :options="robotsOptions" />
          </wx-form-item>

          <wx-form-item
            v-if="structuredData"
            :label="t('card.json-ld')"
            :help="t('card.json-ld-help')"
            :error="jsonLdBroken ? t('card.json-ld-invalid') : undefined"
            :disabled="disabled"
          >
            <wx-code-editor
              v-model="jsonLd"
              language="json"
              lint
              min-height="120px"
              max-height="420px"
            />
          </wx-form-item>
        </div>
      </wx-tab>
    </wx-tabs>
  </div>
</template>

<style scoped>
.wx-seo {
  container-type: inline-size;
}

.wx-seo__fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  padding-block-start: var(--wx-space-12);
}

.wx-seo__count {
  display: flex;
  justify-content: flex-end;
}

.wx-seo__preview {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

/* Not a copy of any one search engine's styling — a reminder of how much of a line survives. */
.wx-seo__snippet {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  padding: var(--wx-space-12);
  border: 1px solid var(--wx-color-border);
  border-radius: var(--wx-radius-md);
  background: var(--wx-color-surface-sunken);
}

.wx-seo__snippet-address {
  color: var(--wx-color-text-muted);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

.wx-seo__snippet-title {
  color: var(--wx-color-primary);
  font-size: var(--wx-font-size-lg);
  overflow-wrap: anywhere;
}

.wx-seo__snippet-text {
  color: var(--wx-color-text);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}
</style>
