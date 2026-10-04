<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSpace,
  WxSwitch,
  WxTab,
  WxTabs,
  type LocalizedValue,
} from '@webx-ui/core'
import SeoCard from './SeoCard.vue'
import SeoFaqList, { type FaqRow } from './SeoFaqList.vue'
import { createSeoApi } from './api'
import { faqEnabled } from './features'
import { useSeoMessages } from './i18n'
import { ruleInput, seoOf } from './rule'
import type { MatchType, SeoFaqItem, SeoUrlRule, SeoValue } from './types'

/**
 * One rule: which addresses it covers, and what it says about them.
 *
 * A dialog rather than a second route, and a template rather than a described screen — the same
 * decision `admins.form` is on, until the screens mechanism has been round the block a few more
 * times. What it edits below the address is `WxSeo`, the same card a content module will get by
 * patching it into its own form.
 */
const props = withDefaults(defineProps<{ rule?: SeoUrlRule | null; mediaField?: Component }>(), {
  rule: null,
  mediaField: undefined,
})

const { resolve, dismiss, open } = useModal<SeoUrlRule>()

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  match_type: 'exact' as MatchType,
  pattern: '',
  priority: 0,
  is_active: true,
})

const seo = ref<SeoValue>({})

/*
 * The page's FAQ (§18.5) sits beside the meta tags, on a tab of the rule rather than in
 * `SeoCard`: the card is shared with every entity, and only an exact rule has questions.
 */
const faqOn = computed(() => faqEnabled(context))
const faq = ref<FaqRow[]>([])
const faqLoading = ref(false)
const tab = ref<'meta' | 'faq'>('meta')

/* Shown for an exact rule; for one that is no longer exact only while it still has questions,
   so they can be taken away before the kind changes — the server refuses it otherwise. */
const showFaq = computed(
  () => faqOn.value && (form.value.match_type === 'exact' || faq.value.length > 0),
)

const editing = computed(() => props.rule !== null)

const kindOptions = computed(() => [
  { value: 'exact', label: t('page.exact') },
  { value: 'mask', label: t('page.mask') },
  { value: 'regex', label: t('page.regex') },
])

watch(
  () => props.rule,
  (rule) => {
    form.value = {
      match_type: rule?.match_type ?? 'exact',
      pattern: rule?.pattern ?? '',
      priority: rule?.priority ?? 0,
      is_active: rule?.is_active ?? true,
    }
    seo.value = seoOf(rule)
    errors.value = {}
    tab.value = 'meta'
    faq.value = rowsOf(rule?.faq ?? [])

    void loadFaq(rule)
  },
  { immediate: true },
)

function rowsOf(items: SeoFaqItem[]): FaqRow[] {
  return items.map((item) => ({ question: item.question, answer: item.answer }))
}

/* The list carries a count, not the questions: a rule is read whole before they are shown. */
async function loadFaq(rule: SeoUrlRule | null): Promise<void> {
  if (!faqOn.value || rule === null || rule.faq !== undefined) return

  faqLoading.value = true

  try {
    faq.value = rowsOf((await api.url(rule.id)).faq ?? [])
  } catch (error) {
    toast.danger(message(error))
  } finally {
    faqLoading.value = false
  }
}

/** Whether a language map has words in any language; a site with no languages sends a string. */
function written(value: LocalizedValue | string): boolean {
  return typeof value === 'string'
    ? value.trim() !== ''
    : Object.values(value ?? {}).some((text) => typeof text === 'string' && text.trim() !== '')
}

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  const input = ruleInput(form.value, seo.value)

  // Sent only where the feature is on — otherwise the server would not read it, and the rows
  // a form never loaded must not look like an emptied FAQ. Untouched new rows are left out.
  if (faqOn.value && !faqLoading.value) {
    input.faq = faq.value
      .filter((row) => written(row.question) || written(row.answer))
      .map((row) => ({ question: row.question, answer: row.answer }))
  }

  try {
    const saved = props.rule
      ? await api.updateUrl(props.rule.id, input)
      : await api.createUrl(input)

    toast.success(t('page.saved'))
    resolve(saved)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('page.failed'))

      if (Object.keys(body.errors).some((key) => key.startsWith('faq.'))) tab.value = 'faq'
    } else {
      toast.danger(message(error, t('page.failed')))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.rule')" :width="860">
    <div class="wx-seo-rule">
      <div class="wx-seo-rule__where">
        <wx-form-item :label="t('page.kind')" :error="errorOf('match_type')">
          <wx-select v-model="form.match_type" :options="kindOptions" />
        </wx-form-item>

        <wx-form-item
          :label="t('page.address')"
          :help="t('page.address-help')"
          :error="errorOf('pattern')"
          class="wx-seo-rule__address"
        >
          <wx-input v-model="form.pattern" placeholder="/catalog/shoes" />
        </wx-form-item>

        <wx-form-item :label="t('page.priority')" :help="t('page.priority-help')">
          <wx-input-number v-model="form.priority" :step="10" />
        </wx-form-item>

        <wx-form-item :label="t('page.state')">
          <wx-switch v-model="form.is_active" />
        </wx-form-item>
      </div>

      <wx-tabs v-if="showFaq" v-model="tab" keep-alive>
        <wx-tab value="meta" :label="t('faq.meta')">
          <seo-card v-model="seo" :media-field="props.mediaField" />
        </wx-tab>

        <wx-tab value="faq" :label="t('faq.tab')" :badge="faq.length || undefined">
          <seo-faq-list
            v-model="faq"
            :errors="errors"
            :locked="form.match_type !== 'exact'"
            :disabled="faqLoading"
          />
        </wx-tab>
      </wx-tabs>

      <seo-card v-else v-model="seo" :media-field="props.mediaField" />
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">
          {{ editing ? t('page.save') : t('page.new-rule') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-seo-rule {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  container-type: inline-size;
}

/* The address and its kind on one line: they are one decision, and reading them apart is
   reading half of it. */
.wx-seo-rule__where {
  display: grid;
  grid-template-columns: 180px 1fr 140px 100px;
  gap: var(--wx-space-12);
  align-items: start;
}

@container (max-width: 720px) {
  .wx-seo-rule__where {
    grid-template-columns: 1fr 1fr;
  }

  .wx-seo-rule__address {
    grid-column: 1 / -1;
  }
}
</style>
