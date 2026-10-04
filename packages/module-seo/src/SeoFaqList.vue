<script setup lang="ts">
import { useTranslate, WxRichTextField } from '@webx-ui/module-admin'
import {
  WxAlert,
  WxButton,
  WxFormItem,
  WxInput,
  WxSortableList,
  WxText,
  localizedValue,
  type LocalizedValue,
} from '@webx-ui/core'
import { useSeoMessages } from './i18n'

/**
 * The FAQ of one page (§18.5): questions and their answers in the order the page prints them.
 *
 * Both halves are in every language the site publishes in — the rule's meta fields are, and a
 * page without a language prefix is every language that has none. The answer is rich text: it is
 * printed as written, and its text without the tags is what the `FAQPage` markup says.
 */
export interface FaqRow {
  /* Rows are keyed by something that survives a drag; the position does not. */
  key: number
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

const emit = defineEmits<{ add: [] }>()

useSeoMessages()

const t = useTranslate('webx-seo')

function errorOf(field: string): string | undefined {
  return props.errors[field]?.[0]
}

function drop(index: number): void {
  rows.value.splice(index, 1)
}

/** What a collapsed row and the drag handle call the question: any language that has words. */
function labelOf(row: FaqRow): string {
  return localizedValue(row.question) || t('faq.question')
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

    <wx-sortable-list
      v-model="rows"
      item-key="key"
      :item-label="labelOf"
      :empty-text="t('faq.none')"
      :drag-label="t('faq.drag')"
      :disabled="props.disabled || props.locked"
    >
      <template #default="{ item, index }">
        <div class="wx-seo-faq__item">
          <wx-form-item :label="t('faq.question')" :error="errorOf(`faq.${index}.question`)">
            <wx-input v-model="item.question" localized :disabled="props.locked" />
          </wx-form-item>

          <wx-form-item :label="t('faq.answer')" :error="errorOf(`faq.${index}.answer`)">
            <wx-rich-text-field
              v-model="item.answer"
              localized
              min-height="96px"
              :tools="['bold', 'italic', 'link', 'bulletList', 'orderedList']"
              :disabled="props.locked"
            />
          </wx-form-item>
        </div>
      </template>

      <template #actions="{ index }">
        <wx-button
          variant="text"
          size="sm"
          icon="trash"
          :aria-label="t('faq.remove')"
          @click="drop(index)"
        />
      </template>
    </wx-sortable-list>

    <div v-if="!props.locked">
      <wx-button
        variant="outline"
        size="sm"
        icon="plus"
        :disabled="props.disabled"
        @click="emit('add')"
      >
        {{ t('faq.add') }}
      </wx-button>
    </div>
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
  padding-block: var(--wx-space-4);
}
</style>
