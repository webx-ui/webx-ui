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
  <div class="wx-inbox-editor" data-wx-fill>
    <wx-skeleton v-if="loading" title :rows="8" />

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
  gap: var(--wx-space-16);
  min-height: 0;
}

.wx-inbox-editor__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
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

/* The tabs take what is left, and what scrolls is inside them. */
.wx-inbox-editor__tabs {
  flex: 1 1 auto;
  min-height: 0;
}
</style>
