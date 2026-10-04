<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate, WxRichTextField } from '@webx-ui/module-admin'
import {
  WxAlert,
  WxFormItem,
  WxInput,
  WxRepeater,
  WxText,
  localizedValue,
  type LocalizedValue,
} from '@webx-ui/core'
import { useSeoMessages } from './i18n'

/**
 * The FAQ of one page (§18.5): questions and their answers in the order the page prints them.
 *
 * A `WxRepeater`, the way every list of records inside a form in the panel is: rows fold to
 * `#2 · the question`, so a page with a dozen questions stays a list one can read and reorder,
 * and removing one is the same red action as anywhere else. Both halves are in every language
 * the site publishes in; the answer is rich text, printed as written, and its text without the
 * tags is what the `FAQPage` markup says.
 */
export interface FaqRow {
  question: LocalizedValue
  answer: LocalizedValue
}

const rows = defineModel<FaqRow[]>({ required: true })

const props = withDefaults(
  defineProps<{
    /** Errors keyed the way the server sends them: `faq.N.question`. */
    errors?: Record<string, string[]>
    /** The rule is not exact: the questions can only be taken away. */
    locked?: boolean
    disabled?: boolean
  }>(),
  { errors: () => ({}), locked: false, disabled: false },
)

useSeoMessages()

const t = useTranslate('webx-seo')

/* The server's refusals by row, so a refused row unfolds and its header says so. */
const rowErrors = computed(() =>
  rows.value.map((_row, index) => {
    const own: Record<string, string[]> = {}

    for (const field of ['question', 'answer']) {
      const found = props.errors[`faq.${index}.${field}`]

      if (found) own[field] = found
    }

    return own
  }),
)

function errorOf(index: number, field: string): string | undefined {
  return props.errors[`faq.${index}.${field}`]?.[0]
}

/** `#2 · the question`, in any language that has words. */
function labelOf(row: FaqRow, index: number): string {
  const question = localizedValue(row.question).trim()

  return question === '' ? `#${index + 1}` : `#${index + 1} · ${question}`
}
</script>

<template>
  <div class="wx-seo-faq">
    <wx-alert
      v-if="props.locked"
      type="warning"
      variant="soft"
      :description="t('faq.exact-only')"
    />
    <wx-text v-else size="sm" tone="muted">{{ t('faq.help') }}</wx-text>

    <!-- Locked, a row can still be removed — that is what the alert asks for — but not added
         or moved: `max` 0 shuts the add button and leaves the remove action alone. -->
    <wx-repeater
      v-model="rows"
      collapsed
      :sortable="!props.locked"
      :max="props.locked ? 0 : undefined"
      :disabled="props.disabled"
      :item-label="labelOf"
      :new-item="() => ({ question: {}, answer: {} })"
      :row-errors="rowErrors"
      :add-label="t('faq.add')"
      :remove-label="t('faq.remove')"
      :drag-label="t('faq.drag')"
      :empty-text="t('faq.none')"
    >
      <template #default="{ item, index, update }">
        <div class="wx-seo-faq__item">
          <wx-form-item :label="t('faq.question')" :error="errorOf(index, 'question')">
            <wx-input
              :model-value="item.question"
              localized
              :disabled="props.locked"
              @update:model-value="(question) => update({ question: question as LocalizedValue })"
            />
          </wx-form-item>

          <wx-form-item :label="t('faq.answer')" :error="errorOf(index, 'answer')">
            <wx-rich-text-field
              :model-value="item.answer"
              localized
              min-height="96px"
              :tools="['bold', 'italic', 'link', 'bulletList', 'orderedList']"
              :disabled="props.locked"
              @update:model-value="
                (answer: unknown) => update({ answer: answer as LocalizedValue })
              "
            />
          </wx-form-item>
        </div>
      </template>
    </wx-repeater>
  </div>
</template>

<style scoped>
.wx-seo-faq {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-seo-faq__item {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}
</style>
