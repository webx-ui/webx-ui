<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  useLocales,
  WxAction,
  WxAlert,
  WxButton,
  WxFormItem,
  WxHeading,
  WxIcon,
  WxInput,
  WxSwitch,
  WxText,
  WxTextarea,
} from '@webx-ui/core'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { RubricInput, RubricRow } from './types'

/**
 * One rubric, in the pane beside the list (§10).
 *
 * Six fields and nothing described: nothing patches a rubric, so there is no screen to extend
 * and no registry to go through. What it does borrow from the described screens are the two
 * fields it has no business drawing itself — the library picker and the SEO card, both looked
 * up in the panel's own type registry. A panel without `module-media` still edits everything
 * else; it simply has no picture.
 *
 * The SEO card starts folded, with a sentence about where the page's title comes from without
 * it. That is the truth for most rubrics — the rules cover them — and a card of ten empty
 * fields under every form teaches editors to fill in ten empty fields (§12).
 */
const props = withDefaults(
  defineProps<{
    /** `null` while a new one is being made: the form of a row that is not in the list yet. */
    rubric: RubricRow | null
    /** The first segment of every blog address, for the line under the slug (§4). */
    prefix?: string
    /** False when the pane is a drawer over the list, which is where the way back lives. */
    inline?: boolean
    disabled?: boolean
  }>(),
  { prefix: '', inline: true, disabled: false },
)

const emit = defineEmits<{
  back: []
  saved: [rubric: RubricRow]
  deleted: []
  articles: [rubric: RubricRow]
}>()

const context = useAdmin()
const api = createBlogApi(context)
const locales = useLocales()
useBlogMessages()

const t = useTranslate('webx-blog')
const message = useErrorText()

/**
 * The fields the library and `module-seo` bring, as the panel registered them.
 *
 * Looked up rather than imported, the same way `module-seo` takes its picture field as an
 * option: this package depends on neither, and a missing one leaves a form that is shorter
 * rather than a form that will not mount.
 */
const mediaField = computed<Component | undefined>(() => context.types['wx-media']?.component)
const seoField = computed<Component | undefined>(() => context.types['wx-seo']?.component)

/** Whatever the panel binds to `wx-seo` — the picture field it picks a share image with. */
const seoBind = computed<Record<string, unknown>>(
  () => context.types['wx-seo']?.bind?.({ id: 'seo', type: 'wx-seo' }) ?? {},
)

const form = ref(blank())
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const seoOpen = ref(false)

function blank() {
  return {
    title: {} as Record<string, string>,
    slug: {} as Record<string, string>,
    lead: {} as Record<string, string>,
    cover: null as { path: string; url?: string } | null,
    is_visible: true,
    seo: {} as Record<string, unknown>,
  }
}

/**
 * The record as it arrived, copied.
 *
 * Copied and not bound, because the pane is a form: what is typed belongs to the form until it
 * is saved, and a list that reorders itself under a half-typed name is a list that loses it.
 */
watch(
  () => props.rubric,
  (rubric) => {
    errors.value = {}
    seoOpen.value = false

    if (rubric === null) {
      form.value = blank()

      return
    }

    form.value = {
      title: { ...rubric.title },
      slug: { ...rubric.slug },
      lead: { ...rubric.lead },
      cover: rubric.cover ? { path: rubric.cover.path, url: rubric.cover.url } : null,
      is_visible: rubric.is_visible,
      seo: { ...rubric.seo },
    }

    seoOpen.value = Object.keys(rubric.seo ?? {}).length > 0
  },
  { immediate: true },
)

const name = computed(
  () => form.value.title[locales.active.value] || props.rubric?.name || t('rubric.new'),
)

/** The address as it stands in the field, not as the registry last heard it. */
const address = computed(() => {
  const slug = form.value.slug[locales.active.value] ?? ''

  return slug === '' ? null : `/${[props.prefix, slug].filter(Boolean).join('/')}`
})

/** A rubric that is on the site at an address that is about to become a different one. */
const moving = computed(() => {
  const current = props.rubric?.path

  return typeof current === 'string' && address.value !== null && address.value !== `/${current}`
})

const held = computed(() => props.rubric?.articles_count ?? 0)

function errorOf(field: string): string | undefined {
  // Laravel names the failing language — `slug.en` — and the field it belongs to is what the
  // form has a place for.
  const key = Object.keys(errors.value).find(
    (name) => name === field || name.startsWith(`${field}.`),
  )

  return key === undefined ? undefined : errors.value[key]?.[0]
}

function payload(): RubricInput {
  return {
    title: form.value.title,
    slug: form.value.slug,
    lead: form.value.lead,
    cover: form.value.cover === null ? null : { path: form.value.cover.path },
    is_visible: form.value.is_visible,
    // Only when it was opened: a save of the rest of the form must not empty a card nobody
    // looked at, and an untouched card is the rules being left in charge (§12).
    ...(seoOpen.value ? { seo: form.value.seo } : {}),
  }
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    const saved =
      props.rubric === null
        ? await api.createRubric(payload())
        : await api.saveRubric(props.rubric.id, payload())

    toast.success(props.rubric === null ? t('rubric.created') : t('rubric.saved'))
    emit('saved', saved)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors

    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

/**
 * Into the bin, and only while it is empty.
 *
 * The button is there and refuses rather than disappearing, because "why can I not delete this"
 * is the question, and a button that is not there does not answer it (§6).
 */
async function remove(): Promise<void> {
  if (props.rubric === null) return

  const agreed = await confirm({
    title: t('rubric.delete-title', { name: props.rubric.name }),
    message: t('rubric.delete-text'),
    confirmText: t('rubric.delete'),
    cancelText: t('rubric.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeRubric(props.rubric.id)
    toast.success(t('rubric.deleted'))
    emit('deleted')
  } catch (error) {
    toast.danger(message(error))
  }
}
</script>

<template>
  <div class="wx-rubric-form" :class="{ 'is-pane': props.inline }">
    <div class="wx-rubric-form__head">
      <!-- On a phone the pane is a screen of its own and the drawer carries no close of its
           own, so the way back has to be here (CLAUDE.md §4). -->
      <wx-action
        v-if="!props.inline"
        icon="arrow-left"
        :title="t('rubric.back')"
        @click="emit('back')"
      />

      <wx-heading :level="3" truncate>{{ name }}</wx-heading>

      <wx-text v-if="props.rubric" size="sm" tone="muted">
        {{ t('rubric.articles', { count: held }) }}
      </wx-text>

      <span class="wx-rubric-form__spacer"></span>

      <wx-button
        v-if="props.rubric && held > 0"
        variant="text"
        size="sm"
        @click="emit('articles', props.rubric)"
      >
        {{ t('rubric.show-articles') }}
      </wx-button>
    </div>

    <div class="wx-rubric-form__body">
      <div class="wx-rubric-form__row">
        <wx-form-item
          class="wx-rubric-form__title"
          :label="t('rubric.field-title')"
          :error="errorOf('title')"
          required
        >
          <wx-input v-model="form.title" localized :disabled="props.disabled" />
        </wx-form-item>

        <wx-form-item
          class="wx-rubric-form__slug"
          :label="t('rubric.field-slug')"
          :error="errorOf('slug')"
          :help="address ?? t('rubric.no-address')"
        >
          <wx-input v-model="form.slug" localized :disabled="props.disabled" />
        </wx-form-item>
      </div>

      <!-- Said before the save rather than in a toast afterwards: an address that is changing
           is the fact that decides whether this is safe to do at all. -->
      <wx-alert
        v-if="moving"
        type="info"
        variant="soft"
        :description="t('rubric.address-moving')"
      />

      <wx-form-item :label="t('rubric.field-lead')" :help="t('rubric.lead-help')" wide>
        <wx-textarea v-model="form.lead" localized :rows="3" :disabled="props.disabled" />
      </wx-form-item>

      <div class="wx-rubric-form__row">
        <component
          :is="mediaField"
          v-if="mediaField"
          v-model="form.cover"
          class="wx-rubric-form__cover"
          :label="t('rubric.field-cover')"
          accept="image"
          :captions="false"
          aspect="16/9"
          :disabled="props.disabled"
        />

        <div class="wx-rubric-form__side">
          <wx-switch
            v-model="form.is_visible"
            :label="t('rubric.visible')"
            :disabled="props.disabled"
          />
          <wx-text size="sm" tone="muted">{{ t('rubric.visible-help') }}</wx-text>
        </div>
      </div>

      <div v-if="seoField" class="wx-rubric-form__seo">
        <template v-if="!seoOpen">
          <wx-text size="sm" weight="medium">{{ t('rubric.seo') }}</wx-text>
          <wx-text size="sm" tone="muted">{{ t('rubric.seo-help') }}</wx-text>
          <wx-button variant="text" size="sm" :disabled="props.disabled" @click="seoOpen = true">
            {{ t('rubric.seo-override', { name }) }}
          </wx-button>
        </template>

        <component
          :is="seoField"
          v-else
          v-model="form.seo"
          v-bind="seoBind"
          :structured-data="false"
          :disabled="props.disabled"
        />
      </div>
    </div>

    <div class="wx-rubric-form__foot">
      <template v-if="props.rubric">
        <wx-button
          variant="outline"
          type="danger"
          :disabled="props.disabled || held > 0"
          @click="remove"
        >
          <!-- `#icon` and not `icon="…"`: the prop does not exist, and the attribute draws
               nothing (CLAUDE.md §4). -->
          <template #icon><wx-icon name="trash" /></template>
          {{ t('rubric.delete') }}
        </wx-button>

        <!-- Why it is out of reach, beside it. A greyed button with no explanation is a bug
             report (§6). -->
        <wx-text v-if="held > 0" size="sm" tone="muted">
          {{ t('rubric.delete-blocked') }}
        </wx-text>
      </template>

      <span class="wx-rubric-form__spacer"></span>

      <wx-button variant="outline" @click="emit('back')">{{ t('rubric.cancel') }}</wx-button>
      <wx-button type="primary" :loading="saving" :disabled="props.disabled" @click="save">
        {{ t('rubric.save') }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-rubric-form {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

.wx-rubric-form__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-10);
  padding: var(--wx-space-12) var(--wx-space-16);
  border-block-end: 1px solid var(--wx-border-color);
}

.wx-rubric-form__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-height: 0;
  padding: var(--wx-space-16);
  overflow-y: auto;
}

.wx-rubric-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-16);
  align-items: flex-start;
}

.wx-rubric-form__title {
  flex: 1 1 260px;
  min-width: 0;
}

.wx-rubric-form__slug {
  flex: 1 1 220px;
  min-width: 0;
}

.wx-rubric-form__cover {
  flex: 0 1 260px;
  min-width: 0;
}

.wx-rubric-form__side {
  display: flex;
  flex: 1 1 200px;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-rubric-form__seo {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-4);
  padding: var(--wx-space-12);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

/* The card, once it is open, is not a note any more: it stops being tinted and takes the
   width, because ten fields inside a highlight read as ten fields inside a warning. */
.wx-rubric-form__seo:has(.wx-seo) {
  align-self: stretch;
  padding: 0;
  background: none;
}

.wx-rubric-form__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-16);
  border-block-start: 1px solid var(--wx-border-color);
}

.wx-rubric-form__spacer {
  flex: 1 1 auto;
}
</style>
