<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxRichTextField } from '@webx-ui/module-admin'
import {
  toast,
  useLocales,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSwitch,
  WxTab,
  WxTabs,
  type TabValue,
} from '@webx-ui/core'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { RubricInput, RubricRow } from './types'

/**
 * One rubric, in a panel over the list (§10).
 *
 * A dialog and not a pane beside the list, because the two are not the same job. The list is
 * dragged — its order *is* the order of the menu on the site — and dragging is a thing done to
 * the whole list at once, with all of it in view. Editing one rubric is the opposite: four
 * tabs' worth of a single record, and the list behind it is context rather than something to
 * keep half a screen for.
 *
 * Three tabs. The words — the name, the address, whether it is on the site, the introduction
 * above its list — are one thing somebody sits down to write, so they are one tab. The picture
 * is not a field but a frame, and beside a column of inputs it either dwarfs them or leaves
 * half a line empty, so it takes a tab. The SEO card is written once and then left to the
 * rules for a year, so it takes the last one.
 *
 * Nothing here is described (§12): nothing patches a rubric, so there is no screen to extend
 * and no registry to go through. What it does borrow are the two fields it has no business
 * drawing itself — the library picker and the SEO card, both looked up in the panel's own type
 * registry. A panel without `module-media` has one tab fewer and edits everything else.
 */
const props = withDefaults(
  defineProps<{
    /** `null` while a new one is being made: the form of a row that is not in the list yet. */
    rubric?: RubricRow | null
    /** The first segment of every blog address, for the line under the slug (§4). */
    prefix?: string
    disabled?: boolean
  }>(),
  { rubric: null, prefix: '', disabled: false },
)

const { open, resolve, dismiss } = useModal<RubricRow>()

const context = useAdmin()
const api = createBlogApi(context)
const locales = useLocales()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

/**
 * The fields the library and `module-seo` bring, as the panel registered them.
 *
 * Looked up rather than imported, the same way `module-seo` takes its picture field as an
 * option: this package depends on neither, and a missing one leaves a dialog with one tab
 * fewer rather than a dialog that will not mount.
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
const tab = ref<TabValue>('content')

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
 * Copied and not bound, because this is a form: what is typed belongs to it until it is saved,
 * and a list that reorders itself under a half-typed name is a list that loses it.
 */
watch(
  () => props.rubric,
  (rubric) => {
    errors.value = {}
    tab.value = 'content'

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

/**
 * Which tab a failing field is on.
 *
 * Without this a 422 can land on a tab nobody is looking at: the message is drawn under a field
 * that is not on screen, and the dialog looks like it refused for no reason (CLAUDE.md §4 — the
 * same trap as a hidden panel).
 */
const TABS: Record<string, TabValue> = {
  title: 'content',
  slug: 'content',
  lead: 'content',
  cover: 'cover',
  seo: 'seo',
}

function errorOf(field: string): string | undefined {
  // Laravel names the failing language — `slug.en` — and the field it belongs to is what the
  // form has a place for.
  const key = Object.keys(errors.value).find(
    (name) => name === field || name.startsWith(`${field}.`),
  )

  return key === undefined ? undefined : errors.value[key]?.[0]
}

/** The first tab holding something that failed, in the order the tabs are read. */
function tabOfErrors(): TabValue | null {
  const order: TabValue[] = ['content', 'cover', 'seo']
  const failing = new Set(
    Object.keys(errors.value)
      .map((key) => TABS[key.split('.')[0] ?? ''])
      .filter((value): value is TabValue => value !== undefined),
  )

  return order.find((value) => failing.has(value)) ?? null
}

function payload(): RubricInput {
  return {
    title: form.value.title,
    slug: form.value.slug,
    lead: form.value.lead,
    cover: form.value.cover === null ? null : { path: form.value.cover.path },
    is_visible: form.value.is_visible,
    // Always, now that the card is a tab of its own: an empty one is the rules being left in
    // charge, which is what the server stores it as (§12).
    seo: form.value.seo,
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
    resolve(saved)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors

    const found = tabOfErrors()

    if (found !== null) tab.value = found

    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <!--
    Height by content rather than fixed: the dialog is as tall as what is in it, and on a short
    screen `WxDialog` stops at the edge and scrolls its body. The cost is a step when the tab
    changes, which is the trade this shape was chosen for.
  -->
  <wx-dialog
    v-model:open="open"
    class="wx-rubric-dialog"
    :title="name"
    :width="880"
    closable
    @close="dismiss"
  >
    <wx-tabs v-model="tab" keep-alive>
      <wx-tab value="content" :label="t('rubric.tab-content')">
        <div class="wx-rubric-dialog__panel">
          <div class="wx-rubric-dialog__row">
            <wx-form-item
              class="wx-rubric-dialog__field"
              :label="t('rubric.field-title')"
              :error="errorOf('title')"
              required
            >
              <wx-input v-model="form.title" localized :disabled="props.disabled" />
            </wx-form-item>

            <wx-form-item
              class="wx-rubric-dialog__field"
              :label="t('rubric.field-slug')"
              :error="errorOf('slug')"
              :help="address ?? undefined"
            >
              <wx-input v-model="form.slug" localized :disabled="props.disabled" />
            </wx-form-item>
          </div>

          <!-- Said before the save rather than in a toast afterwards: an address that is
               changing is the fact that decides whether this is safe to do at all. -->
          <wx-alert
            v-if="moving"
            type="info"
            variant="soft"
            :description="t('rubric.address-moving')"
          />

          <!-- In a form item, so what the switch means is a hint under a control and reads
               like every other hint in the panel rather than like a stray line of grey. -->
          <wx-form-item :help="t('rubric.visible-help')" wide>
            <wx-switch
              v-model="form.is_visible"
              :label="t('rubric.visible')"
              :disabled="props.disabled"
            />
          </wx-form-item>

          <wx-form-item
            :label="t('rubric.field-lead')"
            :error="errorOf('lead')"
            :help="t('rubric.lead-help')"
            wide
          >
            <wx-rich-text-field v-model="form.lead" localized :disabled="props.disabled" />
          </wx-form-item>
        </div>
      </wx-tab>

      <!-- A tab of its own, because the picker is a frame and not a field: beside a column of
           inputs it either dwarfs them or leaves half the line empty. -->
      <wx-tab v-if="mediaField" value="cover" :label="t('rubric.tab-cover')">
        <div class="wx-rubric-dialog__panel">
          <component
            :is="mediaField"
            v-model="form.cover"
            class="wx-rubric-dialog__cover"
            accept="image"
            :captions="false"
            aspect="16/9"
            :disabled="props.disabled"
          />

          <!-- Where the picture ends up, as a note and not as a field's hint: the picker has
               no label of its own here, so there is nothing for a hint to hang under. -->
          <wx-alert type="info" :description="t('rubric.cover-help')" />
        </div>
      </wx-tab>

      <wx-tab v-if="seoField" value="seo" :label="t('rubric.tab-seo')">
        <div class="wx-rubric-dialog__panel">
          <!-- What an empty card means, above the card: it is the answer to "do I have to
               fill this in", and it has to be read before the ten fields, not after them. -->
          <wx-alert type="info" :description="t('rubric.seo-help')" />

          <component
            :is="seoField"
            v-model="form.seo"
            v-bind="seoBind"
            :structured-data="false"
            :disabled="props.disabled"
          />
        </div>
      </wx-tab>
    </wx-tabs>

    <template #footer>
      <wx-button variant="outline" @click="dismiss">{{ t('rubric.cancel') }}</wx-button>
      <wx-button type="primary" :loading="saving" :disabled="props.disabled" @click="save">
        {{ t('rubric.save') }}
      </wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
/* No padding of its own: `WxTabs` already puts a gap under the strip, and a second one on top
   of it pushed the first field a full line away from the tab that named it. */
.wx-rubric-dialog__panel {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-rubric-dialog__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-16);
  align-items: flex-start;
}

.wx-rubric-dialog__field {
  flex: 1 1 240px;
  min-width: 0;
}

/*
 * Given the width the picker would draw a 16/9 cover half a metre across, so it stops at a
 * size a cover is. A maximum and not a width: under it — a narrow dialog on a phone — the
 * frame takes the line rather than leaving a gutter beside itself.
 */
.wx-rubric-dialog__cover {
  max-width: 420px;
}
</style>
