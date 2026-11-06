<script setup lang="ts">
import { computed, inject, ref, useTemplateRef, watch } from 'vue'
import { routeLocationKey } from 'vue-router'
import {
  WxAccordion,
  WxAccordionItem,
  WxAlert,
  WxButton,
  WxDrawer,
  WxHeading,
  WxInput,
  WxSkeleton,
  WxTabs,
  WxText,
  useElementWidth,
} from '@webx-ui/core'
import { useAdmin } from './admin'
import { useErrorText } from './errors'
import { HELP_PANEL_ID, useHelp, type HelpField, type HelpGroup, type HelpPage } from './help'
import { useTranslate } from './i18n'
import { renderMarkdown } from './markdown'

/**
 * The help of a screen, beside the screen: why the section exists, how to do the usual things in
 * it, and what each field of the form does. One for the whole panel, in `AdminShell`; a «?» in a
 * head opens it through `useHelp()` (WEBX_UI_PANEL_HELP.md §4).
 *
 * It lives here rather than in the core because it goes to the API and speaks the panel's
 * dictionary. It is not modal: the page is not dimmed, the focus is not locked and a click in the
 * form leaves it open, because reading it and filling the form in are the same job.
 */
const help = useHelp()
const admin = useAdmin()
const t = useTranslate('webx-admin')
const errorOf = useErrorText()
const route = inject(routeLocationKey, null)

/** More fields than this and the reader needs a search rather than a scroll. */
const SEARCH_FROM = 12

/** Below this the strip of tabs folds into one switch, which a phone's width always is. */
const TABS_FOLD = 360

const open = computed({
  get: () => help?.open.value ?? false,
  set: (value) => {
    if (help) help.open.value = value
  },
})

const page = ref<HelpPage | null>(null)
const failure = ref<string | null>(null)
const loading = ref(false)
const tab = ref<string | number>('')
const query = ref('')

/* A slower answer for the page before must not replace the one on show. */
let ticket = 0

async function load() {
  const key = help?.key.value
  if (!open.value || !key) return

  const mine = ++ticket

  loading.value = true
  failure.value = null

  try {
    const answer = await admin.loadHelp(key)
    if (mine !== ticket) return

    page.value = answer
    tab.value = answer.groups[0]?.id ?? ''
    query.value = ''
    scroller()?.scrollTo?.({ top: 0 })
  } catch (error) {
    if (mine !== ticket) return

    page.value = null
    failure.value = errorOf(error, t('help.error'))
  } finally {
    if (mine === ticket) loading.value = false
  }
}

watch([open, () => help?.key.value, () => admin.i18n.state.locale], load, { immediate: true })

/*
 * Another screen, another page; a screen with none has nothing to say and the panel goes. The
 * key of the route rather than its address: a pane opened inside a list changes the address and
 * not the screen, and its own «?» may be what is on show.
 */
watch(
  () => route?.meta.help,
  (next) => {
    if (!help || !open.value) return

    if (typeof next === 'string' && help.available(next)) {
      help.show(next)
    } else {
      help.close()
    }
  },
)

const inner = useTemplateRef<HTMLElement>('inner')
const heading = useTemplateRef<HTMLElement>('heading')

function scroller(): HTMLElement | null {
  return inner.value?.closest<HTMLElement>('.wx-drawer__content') ?? null
}

/*
 * The panel is measured rather than told: its width is the reader's to drag, and what it came
 * back at from the last session is `WxDrawer`'s to know. The shell gives up exactly that much.
 */
const box = computed(() => inner.value?.closest<HTMLElement>('.wx-drawer') ?? null)
const width = useElementWidth(box)

/* The whole box, border included: `width` is the content box and only says when to look. */
watch([width, box], () => {
  if (help) help.width.value = box.value?.offsetWidth ?? 0
})

/* The title rather than the first link: a reader starts at the top, and so does a screen reader. */
function onOpenFocus(event: Event) {
  event.preventDefault()
  heading.value?.focus()
}

function onCloseFocus(event: Event) {
  event.preventDefault()
  help?.returnFocus()
}

const title = computed(() =>
  page.value ? t('help.heading', { title: page.value.title }) : t('help.title'),
)

/* The text is whole files in one language, so one line says so for all of it. */
const foreign = computed(() => page.value !== null && page.value.locale !== admin.i18n.state.locale)

const fieldCount = computed(() =>
  (page.value?.groups ?? []).reduce((sum, group) => sum + group.fields.length, 0),
)

const needle = computed(() => query.value.trim().toLocaleLowerCase())

function matches(field: HelpField): boolean {
  return [field.label, field.hint, field.body].some((text) =>
    text?.toLocaleLowerCase().includes(needle.value),
  )
}

/* A group outside the tabs has no word of its own, and a tab has to say something. */
const tabs = computed(() =>
  (page.value?.groups ?? []).map((group) => ({
    value: group.id,
    label: group.label ?? page.value?.title,
  })),
)

/** A search looks through every tab at once, so while it runs the tabs say nothing. */
const shown = computed<HelpGroup[]>(() => {
  const groups = page.value?.groups ?? []

  if (needle.value !== '') {
    return groups
      .map((group) => ({ ...group, fields: group.fields.filter(matches) }))
      .filter((group) => group.fields.length > 0)
  }

  return groups.length > 1 ? groups.filter((group) => group.id === tab.value) : groups
})
</script>

<template>
  <wx-drawer
    v-if="help"
    :id="HELP_PANEL_ID"
    v-model:open="open"
    class="wx-help-panel"
    :class="{ 'is-blocking': help.blocking.value }"
    side="right"
    :title="title"
    :size="420"
    :min-size="320"
    :modal="help.blocking.value"
    :overlay="false"
    :close-on-overlay="false"
    :close-label="t('help.close')"
    :resize-label="t('help.resize')"
    resizable
    persist="wx-help"
    closable
    @open-auto-focus="onOpenFocus"
    @close-auto-focus="onCloseFocus"
  >
    <template #title>
      <span ref="heading" class="wx-help-panel__heading" tabindex="-1">{{ title }}</span>
    </template>

    <div ref="inner" class="wx-help-panel__inner" :aria-busy="loading">
      <wx-alert v-if="failure" type="danger" :description="failure">
        <template #actions>
          <wx-button size="sm" @click="load">{{ t('help.retry') }}</wx-button>
        </template>
      </wx-alert>

      <wx-skeleton v-else-if="!page" loading title :rows="6" />

      <template v-else>
        <wx-text v-if="foreign" size="sm" tone="muted">{{ t('help.foreign') }}</wx-text>

        <!--
          `v-html`, and the renderer is why it is safe: it escapes every character of the source
          before it emits a tag, so what lands here contains only the tags it built.
        -->
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="wx-help" v-html="renderMarkdown(page.about)" />

        <section v-if="page.howto.length > 0" class="wx-help-panel__section">
          <wx-heading :level="3">{{ t('help.howto') }}</wx-heading>

          <wx-accordion :key="page.key" multiple>
            <wx-accordion-item
              v-for="(item, index) in page.howto"
              :key="index"
              :value="String(index)"
              :title="item.title"
            >
              <!-- eslint-disable-next-line vue/no-v-html -->
              <div class="wx-help" v-html="renderMarkdown(item.body)" />
            </wx-accordion-item>
          </wx-accordion>
        </section>

        <section v-if="fieldCount > 0" class="wx-help-panel__section">
          <wx-heading :level="3">{{ t('help.fields') }}</wx-heading>

          <wx-input
            v-if="fieldCount > SEARCH_FROM"
            v-model="query"
            type="search"
            :placeholder="t('help.search')"
            :aria-label="t('help.search')"
          />

          <wx-tabs
            v-if="page.groups.length > 1 && needle === ''"
            v-model="tab"
            :items="tabs"
            :collapse-below="TABS_FOLD"
          />

          <wx-text v-if="shown.length === 0" tone="muted">{{ t('help.nothing') }}</wx-text>

          <div v-for="group in shown" :key="group.id" class="wx-help-panel__group">
            <wx-text v-if="needle !== '' && group.label" size="sm" tone="muted" weight="semibold">
              {{ group.label }}
            </wx-text>

            <dl class="wx-help-panel__fields">
              <div v-for="field in group.fields" :key="field.id" class="wx-help-panel__field">
                <dt v-if="field.label" class="wx-help-panel__label">
                  {{ field.label }}
                  <span v-if="field.required" class="wx-help-panel__required" aria-hidden="true">
                    *
                  </span>
                </dt>
                <dd v-if="field.hint" class="wx-help-panel__hint">{{ field.hint }}</dd>
                <!-- eslint-disable-next-line vue/no-v-html -->
                <dd v-if="field.body" class="wx-help" v-html="renderMarkdown(field.body)" />
              </div>
            </dl>
          </div>
        </section>

        <a
          v-if="page.guide"
          class="wx-help-panel__guide"
          :href="page.guide"
          target="_blank"
          rel="noopener"
        >
          {{ t('help.guide') }}
        </a>
      </template>
    </div>
  </wx-drawer>
</template>

<style src="./helpProse.css"></style>

<style>
/* The panel is teleported, so its styles cannot be scoped to the component. */

/*
 * A layer of the page rather than a dialog over it: the dimming of a dialog opened from the form
 * has to lie over this too, because the body takes no clicks then and a panel left bright would
 * look alive and not answer. Modal, it is a dialog like any other and keeps the drawer's layer.
 */
.wx-drawer.wx-help-panel:not(.is-blocking) {
  z-index: var(--wx-z-index-overlay);
}

.wx-help-panel__heading:focus {
  outline: none;
}

.wx-help-panel__inner {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-help-panel__section,
.wx-help-panel__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-help-panel__fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  margin: 0;
}

.wx-help-panel__field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-help-panel__field dd {
  margin: 0;
}

.wx-help-panel__label {
  font-weight: var(--wx-font-weight-semibold);
}

.wx-help-panel__required {
  color: var(--wx-color-danger);
}

.wx-help-panel__field .wx-help-panel__hint {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-help-panel__guide {
  color: var(--wx-color-primary);
}
</style>
