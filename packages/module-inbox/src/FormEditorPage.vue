<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate, WxBackButton } from '@webx-ui/module-admin'
import {
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxBadge,
  WxButton,
  WxCard,
  WxSkeleton,
  WxTab,
  WxTabs,
  WxText,
  type LocalizedValue,
} from '@webx-ui/core'
import FieldList from './FieldList.vue'
import FormAntispam from './FormAntispam.vue'
import FormEmbed from './FormEmbed.vue'
import FormGeneral from './FormGeneral.vue'
import FormNotifications from './FormNotifications.vue'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import type { FormOptions, InboxField, InboxForm } from './types'

/**
 * One form: what it is called, what it asks, who hears about it, and how it is put on a page.
 *
 * Five tabs and one save. The fields are the exception — a question is written in its own
 * dialog and saved on its own, because it is a row with an identity (§2.1) and because a
 * dragged order that waited for a save button would be an order nobody trusts.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/inbox' })

const context = useAdmin()
const api = createInboxApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useInboxMessages()

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const form = ref<InboxForm | null>(null)
const fields = ref<InboxField[]>([])
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const settings = reactive<{ slug: string; title: LocalizedValue; is_enabled: boolean }>({
  slug: '',
  title: {},
  is_enabled: true,
})

const options = ref<FormOptions>({})

const canManage = computed(() => context.can('inbox.manage'))

const id = computed(() => Number(route.params.id))

const name = computed(() =>
  form.value === null ? '' : localizedValue(settings.title, locales.active.value, settings.slug),
)

/** Where a submission would come back to: the section, with this form already chosen. */
const backTo = computed(() => `${props.base}?form=${id.value}`)

async function load(): Promise<void> {
  loading.value = true

  try {
    const loaded = await api.form(id.value)

    form.value = loaded
    fields.value = loaded.fields ?? []
    settings.slug = loaded.slug
    settings.title = { ...loaded.title }
    settings.is_enabled = loaded.is_enabled
    options.value = { ...loaded.options }
  } catch (error) {
    toast.danger(message(error))
    void router.replace(props.base)
  } finally {
    loading.value = false
  }
}

watch(id, () => void load(), { immediate: true })

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    const saved = await api.saveForm(id.value, {
      slug: settings.slug.trim(),
      title: settings.title,
      is_enabled: settings.is_enabled,
      options: options.value,
    })

    form.value = saved
    toast.success(t('panel.saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('panel.failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <!--
    Not a screen that fills its column, whatever the first draft said. `data-wx-fill` makes the
    screen exactly as tall as the window, and nothing inside these tabs scrolls on its own — so
    a form longer than the window grew straight through the box, and the save bar, which is the
    next thing in the column, was drawn across the middle of it with fields still going on
    underneath. What this screen wants is the ordinary thing: the page scrolls, the bar sticks
    to the bottom of it, and `WxMain` gives the screen a floor to push that bar down to.
  -->
  <div class="wx-inbox-editor">
    <!-- On a card, like everything else on this screen: a bare skeleton flush against the
         page is a shape the form that follows it never takes, so the screen jumps twice —
         once when the card appears around it, once when the fields land inside. -->
    <wx-card v-if="loading">
      <wx-skeleton title :rows="8" />
    </wx-card>

    <template v-else-if="form">
      <div class="wx-inbox-editor__head">
        <wx-back-button :to="backTo" :label="t('panel.forms')" />

        <div class="wx-inbox-editor__id">
          <h1 class="wx-inbox-editor__title">{{ name }}</h1>
          <wx-text size="sm" tone="muted" mono>{{ form.slug }}</wx-text>
        </div>

        <wx-badge v-if="!settings.is_enabled" type="default">{{ t('panel.off') }}</wx-badge>
      </div>

      <!--
        Every tab is mounted at once and the hidden ones keep their state: somebody who wrote
        a thank-you, went to look at the fields and came back should find it still written.
      -->
      <wx-tabs class="wx-inbox-editor__tabs" keep-alive>
        <wx-tab value="general" :label="t('panel.tab-general')">
          <form-general v-model:settings="settings" v-model:options="options" :errors="errors" />
        </wx-tab>

        <wx-tab value="fields" :label="t('panel.tab-fields')">
          <field-list v-model="fields" :form="form" :can-manage="canManage" />
        </wx-tab>

        <wx-tab value="notifications" :label="t('panel.tab-notifications')">
          <form-notifications v-model="options" :fields="fields" :errors="errors" />
        </wx-tab>

        <wx-tab value="antispam" :label="t('panel.tab-antispam')">
          <form-antispam v-model="options" />
        </wx-tab>

        <wx-tab value="embed" :label="t('panel.tab-embed')">
          <form-embed :form="form" :slug="settings.slug" :fields="fields" />
        </wx-tab>
      </wx-tabs>

      <wx-action-bar v-if="canManage">
        <wx-button type="primary" :loading="saving" @click="save">{{ t('panel.save') }}</wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-inbox-editor {
  display: flex;
  flex-direction: column;
  /* The panel's own step, which is smaller on a phone than on a desktop. */
  gap: var(--wx-gap, var(--wx-space-16));
}

/*
 * The way out lines up with the name, not with the pair of lines under it.
 *
 * The block beside it is two lines — the name and the address it posts to — so centring the
 * row put the arrow halfway down, level with the gap between them: it read as belonging to
 * the slug rather than to the screen. Aligned to the top and nudged by the difference between
 * the line it stands next to and its own height, it sits on the name.
 */
.wx-inbox-editor__head {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
}

/*
 * The button (30px) is taller than the line it stands beside (25px), so aligning their boxes
 * leaves its centre low; half the difference back up puts the two centres together. `:deep()`
 * because the class is ours but the element it rides is `WxAction`'s, and a scoped rule would
 * be looking for our attribute on somebody else's markup (CLAUDE.md §4).
 */
.wx-inbox-editor__head > :deep(.wx-back-button) {
  margin-block-start: -2px;
}

.wx-inbox-editor__id {
  min-width: 0;
}

.wx-inbox-editor__title {
  margin: 0;
  font-size: var(--wx-font-size-xl);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

/* The tabs take what is left of the column, so the save bar under them is at its bottom
   rather than under the last field. Nothing inside them scrolls on its own — the page does. */
.wx-inbox-editor__tabs {
  flex: 1 1 auto;
}
</style>
