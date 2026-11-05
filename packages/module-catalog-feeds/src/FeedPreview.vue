<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  TemplateRefusal,
  WxAlert,
  WxButton,
  WxCard,
  WxSelect,
  WxSwitch,
  WxText,
} from '@webx-ui/core'
import { useErrorText, useTranslate } from '@webx-ui/module-admin'
import { payloadOf } from './api'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import { parseHint, renderFile, renderItem, type RenderedFeed } from './preview'
import type { FeedProblem, FeedSample, FeedTrial } from './types'

/**
 * The file as it would be (§8.2, §8.3 of the feeds spec): the server counts the values of a few real
 * products once, and the template is rendered here on every key. «Check on the server» runs the
 * check a save runs and shows its verdict, with the line it is about marked.
 *
 * Only what changes the values asks for them again: the language, the selection, the mappings. The
 * templates are not in that question, so typing costs no requests.
 */
defineOptions({ name: 'WxCatalogFeedPreview' })

const DEBOUNCE = 400

const editor = useFeedEditor()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const words = useTranslate('webx-templates')
const message = useErrorText()

const sample = ref<FeedSample | null>(null)
const failed = ref('')
const loading = ref(false)
const asFile = ref(true)
const product = ref<number | null>(null)
const trial = ref<FeedTrial | null>(null)
const trying = ref(false)
const tried = ref('')

const canTry = computed(() => !editor.locked.value)

const parts = computed(() => editor.values.value)
const feedId = computed(() => editor.feed.value?.id ?? null)

/** Everything a value depends on; a template is not among it. */
const question = computed(() => {
  const { locale, filter, stock_map, custom_labels, sources, options, taxonomy } =
    editor.values.value

  return JSON.stringify([
    feedId.value,
    locale,
    filter,
    stock_map,
    custom_labels,
    sources,
    options,
    taxonomy,
  ])
})

let asked = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function load(): Promise<void> {
  if (!canTry.value) return

  const ticket = ++asked

  loading.value = true
  failed.value = ''

  try {
    const answer = await editor.api.sample(payloadOf(withItem()), feedId.value)

    if (ticket !== asked) return

    sample.value = answer

    if (!answer.products.some((one) => one.id === product.value)) {
      product.value = answer.products[0]?.id ?? null
    }
  } catch (error) {
    if (ticket === asked) failed.value = message(error)
  } finally {
    if (ticket === asked) loading.value = false
  }
}

/** The form's rules want a template of a product, which the values do not depend on. */
function withItem() {
  const values = editor.values.value

  return { ...values, name: values.name || '-', item: values.item || '-' }
}

void load()

watch(question, () => {
  clearTimeout(timer)
  timer = setTimeout(() => void load(), DEBOUNCE)
})

onBeforeUnmount(() => {
  asked++
  clearTimeout(timer)
})

type Outcome = { rendered: RenderedFeed } | { refusal: string }

const outcome = computed<Outcome | null>(() => {
  const found = sample.value

  if (found === null) return null

  try {
    if (asFile.value) {
      return { rendered: renderFile(parts.value, found, editor.variables.value ?? undefined) }
    }

    const chosen = found.products.find((one) => one.id === product.value)

    return {
      rendered: chosen
        ? renderItem(parts.value, chosen.values, editor.variables.value ?? undefined)
        : { text: '', unknown: [] },
    }
  } catch (error) {
    if (error instanceof TemplateRefusal) {
      return { refusal: words(`errors.${error.key}`, error.replace) }
    }

    throw error
  }
})

const text = computed(() =>
  outcome.value !== null && 'rendered' in outcome.value ? outcome.value.rendered.text : '',
)

const refusal = computed(() =>
  outcome.value !== null && 'refusal' in outcome.value ? outcome.value.refusal : null,
)

/* Only a whole file parses: one product of a JSON feed may well be a fragment of it. */
const hint = computed(() => (asFile.value ? parseHint(parts.value.format, text.value) : null))

const verdict = computed<FeedProblem | null>(() =>
  trial.value !== null && tried.value === text.value ? (trial.value.errors[0] ?? null) : null,
)

const marked = computed(() => verdict.value?.line ?? hint.value?.line ?? null)

const lines = computed(() => text.value.split('\n'))

const products = computed(() =>
  (sample.value?.products ?? []).map((one) => ({
    value: one.id,
    label: [one.name, one.sku].filter(Boolean).join(' · '),
  })),
)

const rejected = computed(() =>
  Object.entries(trial.value?.rejected ?? {}).map(([reason, count]) => ({
    key: reason,
    line: t('panel.preview-rejected', {
      reason: t(`panel.reason-${reason}`),
      count: String(count),
    }),
  })),
)

async function check(): Promise<void> {
  trying.value = true

  try {
    const answer = await editor.api.preview(payloadOf(editor.values.value), feedId.value)

    trial.value = answer
    tried.value = text.value
    asFile.value = true
  } catch (error) {
    failed.value = message(error)
  } finally {
    trying.value = false
  }
}
</script>

<template>
  <wx-card :title="t('panel.preview')" class="wx-catalog-feed-preview">
    <template v-if="canTry">
      <wx-text size="sm" tone="muted">{{ t('panel.preview-help') }}</wx-text>

      <div class="wx-catalog-feed-preview__controls">
        <wx-switch v-model="asFile" :label="t('panel.preview-as-file')" />

        <wx-select
          v-if="!asFile"
          v-model="product"
          class="wx-catalog-feed-preview__product"
          size="sm"
          :options="products"
          :aria-label="t('panel.preview-product')"
        />

        <wx-button size="sm" variant="outline" :loading="trying" @click="check">
          {{ t('panel.preview-check') }}
        </wx-button>
      </div>

      <wx-alert v-if="failed" type="danger" variant="soft" :description="failed" />

      <wx-alert
        v-for="problem in sample?.errors ?? []"
        :key="problem.message"
        type="danger"
        variant="soft"
        :description="problem.message"
      />

      <wx-alert
        v-if="refusal"
        type="danger"
        variant="soft"
        :title="t('panel.preview-refused')"
        :description="refusal"
      />

      <wx-alert v-if="verdict" type="danger" variant="soft" :description="verdict.message" />

      <wx-alert
        v-else-if="hint"
        type="warning"
        variant="soft"
        :title="t('panel.preview-hint')"
        :description="hint.message"
      />

      <wx-text v-if="sample && sample.products.length === 0" size="sm" tone="muted">
        {{ t('panel.preview-no-products') }}
      </wx-text>

      <div
        v-if="!refusal && text !== ''"
        class="wx-catalog-feed-preview__file"
        role="region"
        tabindex="0"
        :aria-label="t('panel.preview')"
      >
        <div
          v-for="(line, index) in lines"
          :key="index"
          class="wx-catalog-feed-preview__line"
          :class="{ 'is-error': marked === index + 1 }"
        >
          <span class="wx-catalog-feed-preview__number" aria-hidden="true">{{ index + 1 }}</span>
          <span class="wx-catalog-feed-preview__text">{{ line }}</span>
        </div>
      </div>

      <div v-if="trial" class="wx-catalog-feed-preview__counters">
        <wx-text v-if="trial.ok" size="sm" tone="success">{{ t('panel.preview-ok') }}</wx-text>
        <wx-text size="sm" tone="muted">
          {{ t('panel.preview-selected', { count: String(trial.selected ?? 0) }) }}
        </wx-text>
        <wx-text v-for="row in rejected" :key="row.key" size="sm" tone="muted">
          {{ row.line }}
        </wx-text>
      </div>
    </template>

    <wx-text v-else size="sm" tone="muted">{{ t('panel.preview-locked') }}</wx-text>
  </wx-card>
</template>

<style scoped>
.wx-catalog-feed-preview__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8) var(--wx-space-12);
  margin-block: var(--wx-space-8);
}

.wx-catalog-feed-preview__product {
  flex: 1 1 200px;
  min-width: 0;
}

.wx-catalog-feed-preview__file {
  max-height: 520px;
  margin-top: var(--wx-space-8);
  padding-block: var(--wx-space-8);
  overflow: auto;
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

.wx-catalog-feed-preview__line {
  display: flex;
  gap: var(--wx-space-8);
  padding-inline: var(--wx-space-8);
}

.wx-catalog-feed-preview__line.is-error {
  background: var(--wx-color-danger-soft);
}

.wx-catalog-feed-preview__number {
  flex: none;
  min-width: 2ch;
  color: var(--wx-text-muted);
  text-align: right;
  user-select: none;
}

.wx-catalog-feed-preview__text {
  min-width: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.wx-catalog-feed-preview__counters {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  margin-top: var(--wx-space-8);
}
</style>
