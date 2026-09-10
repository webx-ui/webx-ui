<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ColorAreaArea,
  ColorAreaRoot,
  ColorAreaThumb,
  ColorSliderRoot,
  ColorSliderThumb,
  ColorSliderTrack,
  PopoverAnchor,
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  colorToHex,
  convertToHsb,
  getAreaBackgroundStyle,
  getSliderBackgroundStyle,
  parseColor,
} from 'reka-ui'
import type { Color } from 'reka-ui'
import { useFormField } from '../../composables/useFormField'
import type { ColorPickerEmits, ColorPickerProps } from './types'

defineOptions({ name: 'WxColorPicker', inheritAttrs: false })

const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i

const props = withDefaults(defineProps<ColorPickerProps>(), {
  presets: () => [],
  clearable: false,
  placeholder: '#000000',
  teleport: true,
  disabled: undefined,
  readonly: false,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<ColorPickerEmits>()

const model = defineModel<string | null>({ default: null })

const field = useFormField(props)
const open = ref(false)

/** What the text box shows. Kept separate so a half-typed hex is not thrown away. */
const text = ref(model.value ?? '')

watch(model, (value) => {
  text.value = value ?? ''
})

/**
 * A hex parses into an RGB colour, and the picker works in HSB. Both the gradients and
 * the primitives take the space from the object unless told otherwise, so an unconverted
 * colour draws an RGB area — where the brightness gradient runs the other way and the
 * square looks upside down until the first drag replaces the object.
 */
function toWorking(hex: string): Color {
  return convertToHsb(parseColor(hex))
}

/**
 * The picker works on a colour object, not on the hex string.
 *
 * A grey has no hue to speak of, so parsing #919191 back into channels invents one —
 * and re-deriving it on every pointer move is what made the hue strip jump from blue
 * to teal to red as the pointer travelled into the desaturated corner. Holding the
 * object keeps the hue the user chose even where the colour cannot express it.
 */
const working = ref<Color>(
  toWorking(model.value && HEX.test(model.value) ? model.value : '#427edd'),
)

watch(model, (value) => {
  const hex = value && HEX.test(value) ? value : null
  if (!hex || hex === colorToHex(working.value).toLowerCase()) return
  working.value = toWorking(hex)
})

// The space is stated rather than read off the colour, so the gradients cannot drift
// away from the channels the area and the slider are pinned to.
const areaStyle = computed(() =>
  getAreaBackgroundStyle(working.value, 'saturation', 'brightness', 'hsb'),
)
const hueStyle = computed(() => getSliderBackgroundStyle(working.value, 'hue', 'hsb'))

const swatch = computed(() => (model.value && HEX.test(model.value) ? model.value : null))

const classes = computed(() => [
  'wx-color-picker',
  `wx-color-picker--${field.size.value}`,
  {
    [`wx-color-picker--${field.status.value}`]: field.status.value !== 'default',
    'is-open': open.value,
    'is-disabled': field.disabled.value,
  },
])

function commit(value: string | null) {
  if (model.value === value) return
  model.value = value
  emit('change', value)
}

/** Typing is free-form; only a complete hex reaches the model. */
function onInput(event: Event) {
  const value = (event.target as HTMLInputElement).value
  text.value = value
  if (HEX.test(value)) commit(value.toLowerCase())
  else if (value === '') commit(null)
}

/** On blur the box either shows a valid colour or goes back to the last one. */
function onBlur() {
  if (text.value === '') {
    commit(null)
    return
  }
  const candidate = text.value.startsWith('#') ? text.value : `#${text.value}`
  if (HEX.test(candidate)) {
    commit(candidate.toLowerCase())
    text.value = candidate.toLowerCase()
  } else {
    text.value = model.value ?? ''
  }
}

/**
 * The two primitives do not agree on which event carries the new colour: the hue
 * slider reports through `change`, the saturation area through `update:modelValue`.
 * Both are bound on both, and only string payloads are taken — the slider can also
 * hand back a colour object. Committing twice is harmless: an unchanged value is
 * dropped.
 */
function onColor(color: Color) {
  working.value = convertToHsb(color)
  commit(colorToHex(color).toLowerCase())
}

/** Presets and typing come in as hex and have to be parsed back into the picker. */
function onHex(value: string) {
  const hex = value.toLowerCase()
  working.value = toWorking(hex)
  commit(hex)
}

function clear() {
  commit(null)
  text.value = ''
}

function openPicker() {
  if (!field.disabled.value && !props.readonly) open.value = true
}
</script>

<template>
  <popover-root v-model:open="open">
    <div :class="classes">
      <popover-anchor as-child>
        <div class="wx-color-picker__field" @click="openPicker">
          <button
            class="wx-color-picker__swatch"
            type="button"
            :disabled="field.disabled.value"
            :aria-label="ariaLabel ?? 'Pick a colour'"
            :style="swatch ? { background: swatch } : undefined"
            :class="{ 'is-empty': !swatch }"
          />

          <input
            :id="field.id.value"
            v-bind="$attrs"
            class="wx-color-picker__input"
            type="text"
            autocomplete="off"
            spellcheck="false"
            :value="text"
            :name="name"
            :placeholder="placeholder"
            :disabled="field.disabled.value"
            :readonly="readonly"
            :aria-label="ariaLabel"
            :aria-describedby="field.describedBy.value"
            :aria-invalid="field.status.value === 'error' || undefined"
            @input="onInput"
            @blur="onBlur"
          />

          <button
            v-if="clearable && swatch && !field.disabled.value"
            class="wx-color-picker__clear"
            type="button"
            tabindex="-1"
            aria-label="Clear"
            @click.stop="clear"
          >
            &#10005;
          </button>
        </div>
      </popover-anchor>

      <popover-portal :disabled="!teleport">
        <popover-content class="wx-color-picker__panel" :side-offset="4" align="start">
          <!-- Channels are stated: the defaults are RGB, which would move the hue as
               the pointer travels and disagree with the gradient drawn underneath. -->
          <color-area-root
            class="wx-color-picker__area"
            color-space="hsb"
            x-channel="saturation"
            y-channel="brightness"
            :model-value="working"
            @update:color="onColor"
          >
            <color-area-area class="wx-color-picker__area-surface" :style="areaStyle">
              <color-area-thumb class="wx-color-picker__thumb" />
            </color-area-area>
          </color-area-root>

          <color-slider-root
            class="wx-color-picker__hue"
            color-space="hsb"
            channel="hue"
            :model-value="working"
            @update:color="onColor"
          >
            <color-slider-track class="wx-color-picker__hue-track" :style="hueStyle">
              <color-slider-thumb class="wx-color-picker__thumb" />
            </color-slider-track>
          </color-slider-root>

          <div v-if="presets.length" class="wx-color-picker__presets">
            <button
              v-for="preset in presets"
              :key="preset"
              class="wx-color-picker__preset"
              type="button"
              :style="{ background: preset }"
              :aria-label="preset"
              :aria-pressed="model === preset"
              @click="onHex(preset)"
            />
          </div>
        </popover-content>
      </popover-portal>
    </div>
  </popover-root>
</template>

<style scoped>
.wx-color-picker {
  display: block;
  width: 100%;
}

.wx-color-picker__field {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  height: var(--wx-size-control-md);
  padding: 0 var(--wx-space-8) 0 var(--wx-space-6);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-md);
  cursor: text;
  transition: border-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-color-picker--sm .wx-color-picker__field {
  height: var(--wx-size-control-sm);
  font-size: var(--wx-font-size-sm);
}

.wx-color-picker--lg .wx-color-picker__field {
  height: var(--wx-size-control-lg);
  font-size: var(--wx-font-size-lg);
}

.wx-color-picker__field:hover {
  border-color: var(--wx-border-strong);
}

.wx-color-picker.is-open .wx-color-picker__field,
.wx-color-picker__field:focus-within {
  border-color: var(--wx-border-focus);
}

.wx-color-picker--error .wx-color-picker__field {
  border-color: var(--wx-color-danger);
}

.wx-color-picker--success .wx-color-picker__field {
  border-color: var(--wx-color-success);
}

.wx-color-picker--warning .wx-color-picker__field {
  border-color: var(--wx-color-warning);
}

.wx-color-picker.is-disabled .wx-color-picker__field {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-color-picker__swatch {
  flex: 0 0 auto;
  width: 22px;
  height: 22px;
  padding: 0;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-xs);
  cursor: pointer;
}

/* A chequerboard says "no colour" more clearly than an empty box. */
.wx-color-picker__swatch.is-empty {
  background-image:
    linear-gradient(45deg, var(--wx-bg-fill) 25%, transparent 25%),
    linear-gradient(-45deg, var(--wx-bg-fill) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, var(--wx-bg-fill) 75%),
    linear-gradient(-45deg, transparent 75%, var(--wx-bg-fill) 75%);
  background-size: 8px 8px;
  background-position:
    0 0,
    0 4px,
    4px -4px,
    -4px 0;
}

.wx-color-picker__input {
  flex: 1 1 auto;
  min-width: 0;
  height: 100%;
  padding: 0;
  background: transparent;
  border: none;
  outline: none;
  color: inherit;
  font-family: var(--wx-font-family-mono);
  font-size: inherit;
  text-transform: lowercase;
}

.wx-color-picker__input::placeholder {
  color: var(--wx-text-placeholder);
  text-transform: none;
}

.wx-color-picker__clear {
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
  padding: 0;
  background: transparent;
  border: none;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: 1;
  cursor: pointer;
}

.wx-color-picker__clear:hover {
  color: var(--wx-text-default);
}
</style>

<style>
/* Teleported panel. */
.wx-color-picker__panel {
  z-index: var(--wx-z-index-popover);
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
  width: 232px;
  padding: var(--wx-space-10);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
}

.wx-color-picker__area,
.wx-color-picker__area-surface {
  position: relative;
  width: 100%;
  height: 140px;
  border-radius: var(--wx-radius-xs);
}

.wx-color-picker__hue {
  position: relative;
  height: 14px;
}

.wx-color-picker__hue-track {
  /* Reka renders the track as an inline element, where width and height do nothing. */
  display: block;
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: var(--wx-radius-full);
}

.wx-color-picker__thumb {
  width: 14px;
  height: 14px;
  background: transparent;
  border: 2px solid #fff;
  border-radius: var(--wx-radius-full);
  box-shadow: 0 0 0 1px rgb(0 0 0 / 0.35);
}

.wx-color-picker__thumb:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 1px rgb(0 0 0 / 0.35),
    var(--wx-ring-focus);
}

.wx-color-picker__presets {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-color-picker__preset {
  width: 20px;
  height: 20px;
  padding: 0;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-xs);
  cursor: pointer;
}

.wx-color-picker__preset[aria-pressed='true'] {
  box-shadow: 0 0 0 2px var(--wx-color-primary);
}
</style>
