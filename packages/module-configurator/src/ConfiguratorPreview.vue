<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxButton, WxIcon, WxInputNumber, WxText } from '@webx-ui/core'
import { useBuilder } from './builder'
import { isChoice, textIn } from './document'
import {
  chosenOptions,
  mainStep,
  price,
  resolve,
  select,
  set,
  today,
  visible,
} from './engine/engine'
import type {
  ConfiguratorState,
  ConfiguratorValues,
  OptionDoc,
  QuoteLine,
  StepDoc,
} from './engine/types'
import { formatMoney, formatNumber } from './money'

/**
 * The configurator as a visitor goes through it, priced as it is clicked (§13.2): the steps, the
 * picture the rules give, the total — struck through beside the discounted one — and how it is
 * made, line by line.
 *
 * The price is the JS engine's, the same source the widget of the site is built from (§12.4), so
 * what it says is what the site will say; the server's `quote` and these numbers are held
 * together by the parity cases. Until the schema has no problems there is no price — the server
 * would not give one either. What is chosen here can become the configurator's starting choice.
 */
defineOptions({ name: 'WxConfiguratorPreview' })

const props = defineProps<{
  /** The language the texts of the document are shown in. */
  locale: string
}>()

const emit = defineEmits<{
  /** The choice on screen, to start every visitor from. */
  initial: [values: ConfiguratorValues]
}>()

const builder = useBuilder()
const t = useTranslate('webx-configurator')

const schema = computed(() => builder.schema.value)
const day = today()

const state = ref<ConfiguratorState>(resolve(schema.value, {}))

/*
 * The document changed under the preview: what was chosen stays chosen where it still can be,
 * and the sizes are pulled into whatever limits hold now — a visitor's stepper, not a request.
 */
watch(
  schema,
  (next) => {
    state.value = resolve(next, state.value.values, null, true)
  },
  { deep: true },
)

const valid = computed(() => builder.problems.value.length === 0)
const quote = computed(() => (valid.value ? price(schema.value, state.value, day) : null))

const currency = computed(() => schema.value.currency ?? '')

function money(value: number): string {
  return formatMoney(value, currency.value, props.locale)
}

function text(value: StepDoc['label'], fallback = ''): string {
  return textIn(value, props.locale, fallback)
}

/** Only what a visitor sees: the steps that are not hidden, the options that are offered. */
const shownSteps = computed(() =>
  schema.value.steps.filter((step) => step.key && !state.value.hidden.includes(step.key)),
)

function shownOptions(step: StepDoc): OptionDoc[] {
  const offered = state.value.options[step.key] ?? []

  return (step.options ?? []).filter((option) => offered.includes(option.value))
}

function shownFields(step: StepDoc) {
  return (step.fields ?? []).filter((field) => field.key in state.value.limits)
}

function chosen(step: StepDoc, option: OptionDoc): boolean {
  return state.value.values[step.key] === option.value
}

function choose(step: StepDoc, option: OptionDoc): void {
  state.value = select(schema.value, state.value, step.key, option.value)
}

function resize(key: string, value: number | null | undefined): void {
  if (typeof value !== 'number' || !Number.isFinite(value)) return

  state.value = set(schema.value, state.value, key, value)
}

/**
 * «from N» of a card (§7.6): the price of the option at its `defaults`, the rest of the starting
 * choice as it is. Counted from the start and not from what is on screen, so the label does not
 * jump while somebody picks the glass.
 */
const from = computed(() => {
  const prices = new Map<string, number>()

  if (!valid.value) return prices

  const start = resolve(schema.value, {})

  for (const step of schema.value.steps) {
    if (step.type !== 'cards') continue

    for (const option of step.options ?? []) {
      const picked = select(schema.value, start, step.key, option.value)

      prices.set(`${step.key}:${option.value}`, price(schema.value, picked, day).total)
    }
  }

  return prices
})

/**
 * The picture (§10): the first rule whose condition holds, else the chosen option of the main
 * step, else of the first `cards` step on screen. None — no frame at all.
 */
const picture = computed(() => {
  const values = state.value.values
  const offered = state.value.options

  for (const rule of schema.value.previews ?? []) {
    if (rule.image && visible({ when: rule.when ?? {} }, values, offered)) return rule.image
  }

  const main = mainStep(schema.value)
  const chosenList = chosenOptions(schema.value, state.value)
  const ofMain = chosenList.find(([step]) => step === main)?.[1]

  if (typeof ofMain?.image === 'string' && ofMain.image !== '') return ofMain.image

  for (const [step, option] of chosenList) {
    const doc = schema.value.steps.find((one) => one.key === step)

    if (doc?.type === 'cards' && typeof option.image === 'string' && option.image !== '') {
      return option.image
    }
  }

  return null
})

const pictureUrl = computed(() => builder.picture(picture.value))

function swatchStyle(option: OptionDoc): Record<string, string> {
  const image = builder.picture(option.swatchImage)

  if (image) return { backgroundImage: `url("${image}")` }

  return typeof option.swatch === 'string' ? { background: option.swatch } : {}
}

/** A line of the breakdown in words, as the server writes `line` — the site's own words. */
function line(entry: QuoteLine): string {
  const step = schema.value.steps.find((one) => one.key === entry.step)
  const option = step?.options?.find((one) => one.value === entry.option)
  const discountLabel = text(schema.value.discount?.label)

  return t(`site.line.${entry.kind}`, {
    step: step ? text(step.label, text(step.title, step.key)) : '',
    option: option ? text(option.label, option.value) : '',
    rate: formatNumber(entry.rate ?? 0, props.locale, false),
    area: formatNumber(entry.area ?? 0, props.locale, false),
    amount: money(entry.amount),
    label: discountLabel || t('site.discount'),
  })
}

const discounted = computed(
  () => quote.value !== null && quote.value.total_before_discount > quote.value.total,
)

/** Only what a visitor chose and could see: the request carries the same (§11.2). */
function useAsStart(): void {
  emit('initial', { ...state.value.values })
}
</script>

<template>
  <div class="wx-configurator-preview">
    <p v-if="schema.steps.length === 0" class="wx-configurator-preview__empty">
      {{ t('configurator.preview-empty') }}
    </p>

    <template v-else>
      <figure v-if="pictureUrl" class="wx-configurator-preview__picture">
        <img :src="pictureUrl" alt="" />
      </figure>

      <section
        v-for="step in shownSteps"
        :key="step.key"
        class="wx-configurator-preview__step"
        :data-step="step.key"
      >
        <h4 class="wx-configurator-preview__title">
          {{ text(step.title, text(step.label, step.key)) }}
        </h4>
        <p v-if="text(step.hint)" class="wx-configurator-preview__hint">{{ text(step.hint) }}</p>

        <div
          v-if="isChoice(step.type)"
          class="wx-configurator-preview__options"
          :class="`is-${step.type}`"
          role="radiogroup"
          :aria-label="text(step.label, text(step.title, step.key))"
        >
          <button
            v-for="option in shownOptions(step)"
            :key="option.value"
            type="button"
            role="radio"
            class="wx-configurator-preview__option"
            :class="{ 'is-selected': chosen(step, option) }"
            :aria-checked="chosen(step, option)"
            @click="choose(step, option)"
          >
            <span v-if="step.type === 'cards'" class="wx-configurator-preview__card-image">
              <img
                v-if="builder.picture(option.image)"
                :src="builder.picture(option.image)!"
                alt=""
              />
              <wx-icon v-else name="image" />
            </span>
            <span
              v-if="step.type === 'swatches'"
              class="wx-configurator-preview__swatch"
              :style="swatchStyle(option)"
            />
            <span class="wx-configurator-preview__label">{{
              text(option.label, option.value)
            }}</span>
            <span v-if="option.subtitle" class="wx-configurator-preview__subtitle">
              {{ text(option.subtitle) }}
            </span>
            <span
              v-if="step.type === 'cards' && from.has(`${step.key}:${option.value}`)"
              class="wx-configurator-preview__from"
            >
              {{
                t('configurator.preview-from', {
                  price: money(from.get(`${step.key}:${option.value}`)!),
                })
              }}
            </span>
          </button>
        </div>

        <div v-else class="wx-configurator-preview__fields">
          <label
            v-for="field in shownFields(step)"
            :key="field.key"
            class="wx-configurator-preview__field"
          >
            <span class="wx-configurator-preview__field-label">
              {{ text(field.label, field.key) }}
              <span v-if="text(field.unit)" class="wx-configurator-preview__unit">
                {{ text(field.unit) }}
              </span>
            </span>
            <wx-input-number
              :model-value="Number(state.values[field.key])"
              :min="state.limits[field.key]?.[0]"
              :max="state.limits[field.key]?.[1]"
              :step="field.step ?? 1"
              size="sm"
              controls-position="sides"
              :aria-label="text(field.label, field.key)"
              @update:model-value="(value: number | null | undefined) => resize(field.key, value)"
            />
          </label>
        </div>
      </section>

      <div class="wx-configurator-preview__summary">
        <template v-if="quote">
          <div class="wx-configurator-preview__total">
            <span class="wx-configurator-preview__total-label">{{
              t('configurator.preview-total')
            }}</span>
            <span class="wx-configurator-preview__total-sum">
              <s
                v-if="discounted"
                class="wx-configurator-preview__before"
                :title="t('configurator.preview-before')"
              >
                {{ money(quote.total_before_discount) }}
              </s>
              <strong data-total>{{ money(quote.total) }}</strong>
            </span>
          </div>

          <details class="wx-configurator-preview__breakdown" open>
            <summary>{{ t('configurator.preview-breakdown') }}</summary>
            <ul>
              <li
                v-for="(entry, at) in quote.lines"
                :key="at"
                class="wx-configurator-preview__line"
              >
                <span>{{ line(entry) }}</span>
                <span class="wx-configurator-preview__amount">{{ money(entry.amount) }}</span>
              </li>
            </ul>
            <p class="wx-configurator-preview__note">
              <span v-if="quote.area > 0">
                {{
                  t('configurator.preview-area', {
                    area: formatNumber(quote.area, props.locale, false),
                  })
                }}
              </span>
              <span v-if="quote.round > 1">
                {{
                  t('configurator.preview-rounded', {
                    step: formatNumber(quote.round, props.locale),
                  })
                }}
              </span>
            </p>
          </details>
        </template>

        <wx-text v-else size="sm" tone="muted">{{ t('configurator.preview-invalid') }}</wx-text>

        <wx-button
          v-if="!builder.disabled.value"
          size="sm"
          variant="outline"
          block
          @click="useAsStart"
        >
          {{ t('configurator.preview-initial') }}
        </wx-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-configurator-preview {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
  container-type: inline-size;
}

.wx-configurator-preview__empty {
  margin: 0;
  color: var(--wx-text-muted);
}

.wx-configurator-preview__picture {
  margin: 0;
  border-radius: var(--wx-radius-md);
  overflow: clip;
  background: var(--wx-bg-muted);
}

.wx-configurator-preview__picture img {
  display: block;
  width: 100%;
  max-height: 260px;
  object-fit: contain;
}

.wx-configurator-preview__step {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-configurator-preview__title {
  margin: 0;
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  overflow-wrap: anywhere;
}

.wx-configurator-preview__hint {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-configurator-preview__options {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.wx-configurator-preview__options.is-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 128px), 1fr));
}

.wx-configurator-preview__option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  padding: var(--wx-space-8);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  font: inherit;
  font-size: var(--wx-font-size-sm);
  text-align: center;
  cursor: pointer;
  overflow-wrap: anywhere;
}

.wx-configurator-preview__options.is-buttons .wx-configurator-preview__option {
  flex-direction: row;
  padding: var(--wx-space-6) var(--wx-space-12);
}

.wx-configurator-preview__options.is-swatches .wx-configurator-preview__option {
  width: 104px;
  padding: var(--wx-space-4);
  /* A colour's name breaks between words, not inside one: «Прозрачн/ое» reads as a typo. */
  overflow-wrap: break-word;
  border-color: transparent;
  background: transparent;
}

.wx-configurator-preview__option:hover {
  border-color: var(--wx-border-strong);
}

.wx-configurator-preview__option:focus-visible {
  outline: 2px solid var(--wx-color-primary);
  outline-offset: 2px;
}

.wx-configurator-preview__option.is-selected {
  border-color: var(--wx-color-primary);
  box-shadow: 0 0 0 1px var(--wx-color-primary);
}

.wx-configurator-preview__options.is-swatches .wx-configurator-preview__option.is-selected {
  box-shadow: none;
}

.wx-configurator-preview__card-image {
  display: grid;
  place-items: center;
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-muted);
  color: var(--wx-text-muted);
  overflow: clip;
}

.wx-configurator-preview__card-image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.wx-configurator-preview__swatch {
  width: 40px;
  height: 40px;
  border-radius: var(--wx-radius-full);
  border: 1px solid var(--wx-border-default);
  background-size: cover;
  background-position: center;
}

.wx-configurator-preview__option.is-selected .wx-configurator-preview__swatch {
  box-shadow:
    0 0 0 2px var(--wx-bg-surface),
    0 0 0 4px var(--wx-color-primary);
}

.wx-configurator-preview__label {
  font-weight: var(--wx-font-weight-medium);
}

.wx-configurator-preview__subtitle,
.wx-configurator-preview__from {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-configurator-preview__fields {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 150px), 1fr));
  gap: var(--wx-space-12);
}

.wx-configurator-preview__field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-configurator-preview__field-label {
  font-size: var(--wx-font-size-sm);
}

.wx-configurator-preview__unit {
  color: var(--wx-text-muted);
}

.wx-configurator-preview__summary {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  padding-top: var(--wx-space-12);
  border-top: 1px solid var(--wx-border-default);
}

.wx-configurator-preview__total {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-configurator-preview__total-label {
  color: var(--wx-text-muted);
}

.wx-configurator-preview__total-sum {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-8);
}

.wx-configurator-preview__total-sum strong {
  font-size: var(--wx-font-size-xl);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-configurator-preview__before {
  color: var(--wx-text-muted);
}

.wx-configurator-preview__breakdown summary {
  cursor: pointer;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-configurator-preview__breakdown ul {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: var(--wx-space-8) 0 0;
  padding: 0;
  list-style: none;
}

.wx-configurator-preview__line {
  display: flex;
  justify-content: space-between;
  gap: var(--wx-space-12);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

.wx-configurator-preview__amount {
  flex: none;
  font-variant-numeric: tabular-nums;
}

.wx-configurator-preview__note {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
  margin: var(--wx-space-8) 0 0;
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}
</style>
