<script setup lang="ts">
import { computed, inject, ref, type Component } from 'vue'
import { adminKey, useTranslate } from '@webx-ui/module-admin'
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
     * Not imported: this package does not depend on the media one, and a panel without a library
     * still edits everything else about a page. Not usually handed in either — the card looks
     * `wx-media` up in the panel's own registry, which is where the module that has the library
     * puts it. This prop is for a caller that wants a different field than the registered one.
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

/*
 * Injected rather than asked for with `useAdmin()`, which throws outside a panel: this card is
 * also mounted by the docs and by its own tests, and there it has to draw everything but the
 * picture rather than fail.
 */
const admin = inject(adminKey, null)

/**
 * The field the picture is picked with: whatever this card was given, or `wx-media` as the panel
 * registered it.
 *
 * Looked up rather than only handed in, because a panel that installed the library has already
 * said so once — and saying it twice is a thing to forget. Forgetting it is silent: every other
 * SEO field is there, and the picture is simply missing from one screen and present on another.
 */
const picker = computed<Component | undefined>(
  () => props.mediaField ?? admin?.types['wx-media']?.component,
)
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

/**
 * What a share card would say, with the fallbacks the fields promise: an empty share title
 * takes the page's title, an empty share description takes its description.
 *
 * The picture is the address the server sent beside the key — `og_image.url`, worked out by
 * whoever owns `wx-media`. That is the whole reason it travels with the value, so the preview
 * costs no request and works on a page opened cold, not only on one where the picture was
 * picked a moment ago.
 */
const share = computed(() => ({
  image: image.value?.url ?? null,
  title: inLocale(ogTitle.value as LocalizedValue | undefined) || preview.value.title,
  description:
    inLocale(ogDescription.value as LocalizedValue | undefined) || preview.value.description,
  address: preview.value.address,
}))

const hasShare = computed(
  () => share.value.image !== null || share.value.title !== '' || share.value.description !== '',
)

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
            :is="picker"
            v-if="picker"
            v-model="image"
            class="wx-seo__image"
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

          <!-- The same idea as the snippet on the first tab, for the other place a page is
               read without being opened. -->
          <div v-if="hasShare" class="wx-seo__preview">
            <wx-text size="sm" tone="muted">{{ t('card.share-preview') }}</wx-text>

            <div class="wx-seo__share">
              <div class="wx-seo__share-image">
                <!-- Empty is not a mistake to point at: a site fills the picture in from the
                     record itself, and only it knows with what. Said rather than left blank,
                     because a blank rectangle reads as "nothing will be shown". -->
                <img v-if="share.image" :src="share.image" alt="" />
                <wx-text v-else size="sm" tone="muted">{{ t('card.share-auto') }}</wx-text>
              </div>

              <div class="wx-seo__share-body">
                <div v-if="share.address" class="wx-seo__share-address">{{ share.address }}</div>
                <div class="wx-seo__share-title">{{ share.title }}</div>
                <div v-if="share.description" class="wx-seo__share-text">
                  {{ share.description }}
                </div>
              </div>
            </div>
          </div>
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

/*
 * As wide as the fields it stands with.
 *
 * The picture is the one thing in this card that is not inside a `WxFormItem` — it draws its own
 * label — so the cap a form item puts on its control never reached it, and a share image ran the
 * whole width of the card while the title under it stopped at 640. The same variable, so a
 * screen that widens its fields widens this too.
 */
.wx-seo__image {
  max-width: var(--wx-field-max-width, 640px);
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
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

.wx-seo__snippet-address {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

.wx-seo__snippet-title {
  color: var(--wx-color-primary);
  font-size: var(--wx-font-size-lg);
  overflow-wrap: anywhere;
}

.wx-seo__snippet-text {
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

/* The share card, at the width of the fields it is made of. */
.wx-seo__share {
  max-width: var(--wx-field-max-width, 640px);
  overflow: hidden;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

/*
 * 1.91:1 is the shape every network crops an unknown picture to, so it is the shape worth
 * showing: a preview in any other proportion promises a framing the reader will not get.
 */
.wx-seo__share-image {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1.91 / 1;
  padding: var(--wx-space-16);
  border-block-end: 1px solid var(--wx-border-default);
  background: var(--wx-bg-muted);
  text-align: center;
}

.wx-seo__share-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wx-seo__share-body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  padding: var(--wx-space-12);
}

.wx-seo__share-address {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  text-transform: uppercase;
  overflow-wrap: anywhere;
}

.wx-seo__share-title {
  color: var(--wx-text-default);
  font-weight: var(--wx-font-weight-medium);
  overflow-wrap: anywhere;
}

.wx-seo__share-text {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}
</style>
