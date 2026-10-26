<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import {
  WxButton,
  WxColorPicker,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSegmented,
  type LocalizedValue,
} from '@webx-ui/core'
import { WxMediaField } from '@webx-ui/module-media'
import ConditionsEditor from './ConditionsEditor.vue'
import { conditionErrors, errorsAt, useBuilder } from './builder'
import { fieldKeys, localized, renameValue, setText, stepsAbove } from './document'
import { isSwatch } from './engine/validator'
import type { Condition, Price } from './engine/types'

/**
 * One option of a step of choices, unfolded (§13.2): its value and words, the picture of a card
 * or the swatch, the price in three numbers, the area formula on the main step, the sizes it puts
 * in and the limits it holds, and when it is offered at all.
 *
 * Edits go straight into the document; a value renamed takes the conditions that chose it along.
 */
defineOptions({ name: 'WxConfiguratorOption' })

const props = defineProps<{ step: number; index: number; main: boolean }>()

const builder = useBuilder()
const t = useTranslate('webx-configurator')

const doc = computed(() => builder.schema.value.steps[props.step]!)
const option = computed(() => doc.value.options![props.index]!)
const path = computed(() => `steps.${props.step}.options.${props.index}`)
const locale = builder.locale
const disabled = builder.disabled

const errors = (suffix: string) => errorsAt(builder, `${path.value}.${suffix}`)

/* The value is renamed on leaving the field, not on every key: halfway through typing it may be
   another option's value, and the conditions would follow the wrong one. */
const value = ref(option.value.value)
watch(
  () => option.value.value,
  (next) => (value.value = next),
)

function commitValue(): void {
  const next = value.value.trim()
  const before = option.value.value

  if (next === before) return

  const taken = (doc.value.options ?? []).some(
    (one, at) => at !== props.index && one.value === next,
  )

  if (!taken && next !== '') renameValue(builder.schema.value, doc.value.key, before, next)

  option.value.value = next
}

function text(key: 'label' | 'subtitle', next: LocalizedValue | string | number | undefined): void {
  setText(option.value, key, typeof next === 'number' ? String(next) : (next ?? null))
}

/* ------------------------------------------------------------------- pictures and swatches */

function picture(key: 'image' | 'swatchImage') {
  const at = option.value[key]

  return typeof at === 'string' && at !== '' ? { path: at } : null
}

function setPicture(key: 'image' | 'swatchImage', next: { path?: string } | null): void {
  const at = next?.path ?? ''

  if (at !== '') option.value[key] = at
  else if (key === 'image') option.value.image = null
  else delete option.value.swatchImage
}

type SwatchMode = 'colour' | 'gradient' | 'picture'

const mode = ref<SwatchMode>(
  option.value.swatchImage
    ? 'picture'
    : typeof option.value.swatch === 'string' && option.value.swatch.includes('gradient(')
      ? 'gradient'
      : 'colour',
)

const modes = computed(() => [
  { value: 'colour', label: t('configurator.swatch-colour') },
  { value: 'gradient', label: t('configurator.swatch-gradient') },
  { value: 'picture', label: t('configurator.swatch-picture') },
])

/** Switching what the swatch is made of keeps one of them: `swatchImage` wins over `swatch` (§5.5). */
function setMode(next: unknown): void {
  mode.value = next as SwatchMode

  if (next !== 'picture') delete option.value.swatchImage
  if (next === 'gradient' && !option.value.swatch?.includes('gradient(')) {
    option.value.swatch = `linear-gradient(135deg, ${option.value.swatch || '#cccccc'}, #ffffff)`
  }
  if (next === 'colour' && option.value.swatch?.includes('gradient('))
    option.value.swatch = '#cccccc'
}

function setSwatch(next: string | number | LocalizedValue | null | undefined): void {
  const colour = typeof next === 'string' ? next.trim() : ''

  if (colour === '') delete option.value.swatch
  else option.value.swatch = colour
}

const gradientError = computed(() =>
  mode.value === 'gradient' && option.value.swatch && !isSwatch(option.value.swatch)
    ? t('errors.swatch.invalid')
    : undefined,
)

/* ------------------------------------------------------------------------------- price */

function amount(key: keyof Price): number | null {
  const number = option.value.price?.[key]

  return typeof number === 'number' ? number : null
}

/** An empty price is no price: the key goes, and an option with none is free. */
function setAmount(key: keyof Price, next: number | null | undefined): void {
  const price: Price = { ...(option.value.price ?? {}) }

  if (typeof next !== 'number' || !Number.isFinite(next)) delete price[key]
  else price[key] = next

  if (Object.keys(price).length === 0) delete option.value.price
  else option.value.price = price
}

/* -------------------------------------------------------------- formula, defaults, limits */

const sizes = computed(() => fieldKeys(builder.schema.value))

function setArea(next: string | number | LocalizedValue | undefined): void {
  option.value.area = typeof next === 'string' ? next : String(next ?? '')
}

function insertKey(key: string): void {
  const formula = option.value.area ?? ''

  option.value.area =
    formula === '' || /[+\-*/(]\s*$/.test(formula) ? `${formula}${key}` : `${formula}*${key}`
}

function preset(key: string): number | null {
  const number = option.value.defaults?.[key]

  return typeof number === 'number' ? number : null
}

function setPreset(key: string, next: number | null | undefined): void {
  const defaults = { ...(option.value.defaults ?? {}) }

  if (typeof next !== 'number' || !Number.isFinite(next)) delete defaults[key]
  else defaults[key] = next

  if (Object.keys(defaults).length === 0) delete option.value.defaults
  else option.value.defaults = defaults
}

function limit(key: string, at: 0 | 1): number | null {
  const pair = option.value.limits?.[key]

  return Array.isArray(pair) && typeof pair[at] === 'number' ? pair[at] : null
}

/** A limit is a pair or nothing: one end given takes the other from the field itself. */
function setLimit(key: string, at: 0 | 1, next: number | null | undefined): void {
  const limits = { ...(option.value.limits ?? {}) }
  const field = builder.schema.value.steps
    .flatMap((step) => step.fields ?? [])
    .find((one) => one.key === key)
  const pair: [number | null, number | null] = [limit(key, 0), limit(key, 1)]

  pair[at] = typeof next === 'number' && Number.isFinite(next) ? next : null

  if (pair[0] === null && pair[1] === null) delete limits[key]
  else limits[key] = [pair[0] ?? field?.min ?? 0, pair[1] ?? field?.max ?? 0]

  if (Object.keys(limits).length === 0) delete option.value.limits
  else option.value.limits = limits
}

/* ------------------------------------------------------------------------------ conditions */

const above = computed(() => stepsAbove(builder.schema.value, props.step))

function setCondition(which: 'when' | 'unless', next: Condition | undefined): void {
  if (next === undefined) delete option.value[which]
  else option.value[which] = next
}

const opened = ref(
  Boolean(option.value.when || option.value.unless || option.value.defaults || option.value.limits),
)
</script>

<template>
  <div class="wx-configurator-option" :data-path="path">
    <div class="wx-configurator-option__grid">
      <wx-form-item :label="t('configurator.option-label')" :error="errors('label')" required>
        <wx-input
          :model-value="localized(option.label, locale)"
          localized
          size="sm"
          :disabled="disabled"
          @update:model-value="(next) => text('label', next)"
        />
      </wx-form-item>

      <wx-form-item
        :label="t('configurator.option-value')"
        :help="t('configurator.option-value-help')"
        :error="errors('value')"
        required
      >
        <wx-input
          v-model="value"
          size="sm"
          :disabled="disabled"
          @change="commitValue"
          @blur="commitValue"
        />
      </wx-form-item>

      <wx-form-item :label="t('configurator.option-subtitle')" :error="errors('subtitle')">
        <wx-input
          :model-value="localized(option.subtitle, locale)"
          localized
          size="sm"
          :disabled="disabled"
          @update:model-value="(next) => text('subtitle', next)"
        />
      </wx-form-item>
    </div>

    <wx-form-item
      v-if="doc.type === 'cards'"
      :label="t('configurator.option-image')"
      :error="errors('image')"
      required
    >
      <wx-media-field
        :model-value="picture('image')"
        accept="image"
        :aspect="null"
        :height="120"
        :width="200"
        :captions="false"
        :disabled="disabled"
        @update:model-value="(next) => setPicture('image', next)"
      />
    </wx-form-item>

    <wx-form-item
      v-if="doc.type === 'swatches'"
      :label="t('configurator.option-swatch')"
      :error="[...errors('swatch'), ...errors('swatchImage')]"
    >
      <div class="wx-configurator-option__swatch">
        <wx-segmented
          :model-value="mode"
          :options="modes"
          size="sm"
          :disabled="disabled"
          @update:model-value="setMode"
        />
        <wx-color-picker
          v-if="mode === 'colour'"
          :model-value="option.swatch ?? null"
          size="sm"
          :disabled="disabled"
          @update:model-value="setSwatch"
        />
        <wx-form-item
          v-else-if="mode === 'gradient'"
          :help="t('configurator.swatch-gradient-help')"
          :error="gradientError"
        >
          <wx-input
            :model-value="option.swatch ?? ''"
            size="sm"
            :disabled="disabled"
            @update:model-value="setSwatch"
          />
        </wx-form-item>
        <wx-media-field
          v-else
          :model-value="picture('swatchImage')"
          accept="image"
          :aspect="null"
          :height="80"
          :width="120"
          :captions="false"
          :disabled="disabled"
          @update:model-value="(next) => setPicture('swatchImage', next)"
        />
      </div>
    </wx-form-item>

    <wx-form-item
      :label="t('configurator.price')"
      :help="t('configurator.price-help')"
      :error="errors('price')"
    >
      <div class="wx-configurator-option__price">
        <label
          v-for="key in ['fixed', 'perM2', 'percent'] as const"
          :key="key"
          class="wx-configurator-option__number"
        >
          <span>{{ t(`configurator.price-${key === 'perM2' ? 'per-m2' : key}`) }}</span>
          <wx-input-number
            :model-value="amount(key)"
            size="sm"
            :controls="false"
            :disabled="disabled"
            :status="errors(`price.${key}`).length > 0 ? 'error' : 'default'"
            @update:model-value="(next: number | null | undefined) => setAmount(key, next)"
          />
        </label>
      </div>
    </wx-form-item>

    <wx-form-item
      v-if="props.main || option.area !== undefined"
      :label="t('configurator.option-area')"
      :help="t('configurator.option-area-help')"
      :error="errors('area')"
      :required="props.main"
    >
      <div class="wx-configurator-option__area">
        <wx-input
          :model-value="option.area ?? ''"
          size="sm"
          class="wx-configurator-option__formula"
          :disabled="disabled"
          placeholder="(w+d)*h/1e6"
          @update:model-value="setArea"
        />
        <div v-if="!disabled" class="wx-configurator-option__keys">
          <wx-button
            v-for="key in sizes"
            :key="key"
            size="sm"
            variant="outline"
            :title="t('configurator.insert-key', { key })"
            @click="insertKey(key)"
          >
            {{ key }}
          </wx-button>
        </div>
      </div>
    </wx-form-item>

    <wx-button
      v-if="!opened"
      size="sm"
      variant="text"
      class="wx-configurator-option__more"
      @click="opened = true"
    >
      + {{ t('configurator.option-defaults') }} · {{ t('configurator.option-limits') }} ·
      {{ t('configurator.when') }}
    </wx-button>

    <template v-else>
      <wx-form-item
        :label="t('configurator.option-defaults')"
        :help="
          sizes.length > 0 ? t('configurator.option-defaults-help') : t('configurator.no-fields')
        "
        :error="errors('defaults')"
      >
        <div class="wx-configurator-option__sizes">
          <label v-for="key in sizes" :key="key" class="wx-configurator-option__number">
            <span>{{ key }}</span>
            <wx-input-number
              :model-value="preset(key)"
              size="sm"
              :controls="false"
              :disabled="disabled"
              :status="errors(`defaults.${key}`).length > 0 ? 'error' : 'default'"
              @update:model-value="(next: number | null | undefined) => setPreset(key, next)"
            />
          </label>
        </div>
      </wx-form-item>

      <wx-form-item
        v-if="sizes.length > 0"
        :label="t('configurator.option-limits')"
        :help="t('configurator.option-limits-help')"
        :error="errors('limits')"
      >
        <div class="wx-configurator-option__sizes">
          <div v-for="key in sizes" :key="key" class="wx-configurator-option__limit">
            <span>{{ key }}</span>
            <wx-input-number
              :model-value="limit(key, 0)"
              size="sm"
              :controls="false"
              :placeholder="t('configurator.limits-min')"
              :aria-label="`${key} ${t('configurator.limits-min')}`"
              :disabled="disabled"
              @update:model-value="(next: number | null | undefined) => setLimit(key, 0, next)"
            />
            <wx-input-number
              :model-value="limit(key, 1)"
              size="sm"
              :controls="false"
              :placeholder="t('configurator.limits-max')"
              :aria-label="`${key} ${t('configurator.limits-max')}`"
              :disabled="disabled"
              @update:model-value="(next: number | null | undefined) => setLimit(key, 1, next)"
            />
          </div>
        </div>
      </wx-form-item>

      <conditions-editor
        :model-value="option.when"
        :label="t('configurator.when')"
        :steps="above"
        :locale="locale"
        :disabled="disabled"
        :errors="conditionErrors(builder, `${path}.when`)"
        @update:model-value="(next) => setCondition('when', next)"
      />
      <conditions-editor
        v-if="above.length > 0"
        :model-value="option.unless"
        :label="t('configurator.unless')"
        :steps="above"
        :locale="locale"
        :disabled="disabled"
        :errors="conditionErrors(builder, `${path}.unless`)"
        @update:model-value="(next) => setCondition('unless', next)"
      />
    </template>
  </div>
</template>

<style scoped>
.wx-configurator-option {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  container-type: inline-size;
}

.wx-configurator-option__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
  gap: var(--wx-space-12);
}

.wx-configurator-option__swatch,
.wx-configurator-option__area {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-configurator-option__price,
.wx-configurator-option__sizes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 120px), 1fr));
  gap: var(--wx-space-8);
}

.wx-configurator-option__number,
.wx-configurator-option__limit {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-configurator-option__limit {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.wx-configurator-option__limit > span {
  grid-column: 1 / -1;
}

.wx-configurator-option__formula :deep(input) {
  font-family: var(--wx-font-family-mono);
}

.wx-configurator-option__keys {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-configurator-option__more {
  align-self: flex-start;
}
</style>
