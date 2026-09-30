<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { localizedValue, useLocales, WxBadge, type LocalizedValue } from '@webx-ui/core'
import { useCategoryEditor } from '@webx-ui/module-admin'
import { TONES, toneBadge, type Tone } from './tones'

/**
 * The tone of a label or a stock status (`wx-catalog-tone`): six tags, one per tone, each in its
 * own — picking one is picking what it looks like — and beside them the record itself as the list
 * of products will show it, with the name that is being typed above.
 *
 * The words of the tones are the screen's (`props.options`, one per tone): the server says them
 * in the panel's language, and a tone without a word is shown by its key.
 */
defineOptions({ name: 'WxCatalogToneField' })

const props = withDefaults(
  defineProps<{
    options?: Array<{ label: string; value: string }>
    disabled?: boolean
    ariaLabel?: string
  }>(),
  { options: () => [], disabled: false, ariaLabel: undefined },
)

const value = defineModel<string | null>({ default: null })

const locales = useLocales()
const editor = useCategoryEditor()

const root = useTemplateRef<HTMLElement>('root')

/*
 * The group is named by the label of the form item it stands in. The core does not hand a field
 * built outside it the item's ids (`useFormField` is its own), and a group of radios cannot be the
 * target of a label's `for` — so the label is found where it is drawn.
 */
const labelledBy = ref<string | undefined>()

onMounted(() => {
  labelledBy.value =
    root.value?.closest('.wx-form-item')?.querySelector('.wx-form-item__label')?.id || undefined
})

const chosen = computed<Tone>(() =>
  (TONES as readonly string[]).includes(value.value ?? '') ? (value.value as Tone) : 'neutral',
)

const words = computed(() => new Map(props.options.map((one) => [one.value, one.label])))

/** The record's name as typed, in the language the form is showing; the tone's word until then. */
const sample = computed(() => {
  const title = editor?.values.value.title as LocalizedValue | string | null | undefined

  return localizedValue(title, locales.active.value, '') || words.value.get(chosen.value) || ''
})

function pick(tone: Tone): void {
  if (!props.disabled) value.value = tone
}

/* Arrows move the choice and the focus with it, as in any group of radio buttons. */
function step(delta: number): void {
  const at = TONES.indexOf(chosen.value)

  pick(TONES[(at + delta + TONES.length) % TONES.length]!)
  void nextTick(() => root.value?.querySelector<HTMLElement>('[aria-checked="true"]')?.focus())
}
</script>

<template>
  <div ref="root" class="wx-catalog-tone">
    <div
      class="wx-catalog-tone__choices"
      role="radiogroup"
      :aria-label="ariaLabel"
      :aria-labelledby="ariaLabel ? undefined : labelledBy"
      :aria-disabled="disabled || undefined"
    >
      <button
        v-for="tone in TONES"
        :key="tone"
        type="button"
        role="radio"
        class="wx-catalog-tone__choice"
        :class="{ 'is-chosen': tone === chosen }"
        :aria-checked="tone === chosen"
        :tabindex="tone === chosen ? 0 : -1"
        :disabled="disabled"
        :data-tone="tone"
        @click="pick(tone)"
        @keydown.right.prevent="step(1)"
        @keydown.down.prevent="step(1)"
        @keydown.left.prevent="step(-1)"
        @keydown.up.prevent="step(-1)"
      >
        <wx-badge :type="toneBadge(tone)" :variant="tone === chosen ? 'solid' : 'soft'" round>
          {{ words.get(tone) ?? tone }}
        </wx-badge>
      </button>
    </div>

    <span v-if="sample" class="wx-catalog-tone__sample" aria-hidden="true">
      <wx-badge :type="toneBadge(chosen)" size="sm">{{ sample }}</wx-badge>
    </span>
  </div>
</template>

<style scoped>
.wx-catalog-tone {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-catalog-tone__choices {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}

.wx-catalog-tone__choice {
  display: inline-flex;
  padding: 0;
  border: 0;
  border-radius: var(--wx-radius-full);
  background: none;
  font: inherit;
  cursor: pointer;
}

.wx-catalog-tone__choice:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.wx-catalog-tone__choice:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 2px;
}

/* The record as the list will show it, set apart from the choices by a rule rather than a label. */
.wx-catalog-tone__sample {
  display: inline-flex;
  padding-left: var(--wx-space-12);
  border-left: 1px solid var(--wx-border-default);
}
</style>
