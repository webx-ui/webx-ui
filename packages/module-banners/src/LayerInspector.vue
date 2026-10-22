<script setup lang="ts">
import { computed } from 'vue'
import {
  useLocales,
  WxButton,
  WxColorPicker,
  WxFormItem,
  WxIcon,
  WxInput,
  WxInputNumber,
  WxSegmented,
  WxSelect,
  WxSwitch,
  WxText,
  WxTextarea,
  type LocalizedValue,
  WxPopconfirm,
} from '@webx-ui/core'
import { useRemoveConfirm, useTranslate, WxLinkField, type LinkValue } from '@webx-ui/module-admin'
import { WxMediaField } from '@webx-ui/module-media'
import {
  clone,
  ENTRANCES,
  FRAME_LIMITS,
  LOOPS,
  ORIGINS,
  unknownFields,
  type BannerLayer,
  type LayerFrame,
  type LayerOrigin,
  type LayerStyle,
  type LayerView,
  type MediaRef,
} from './layers'

/**
 * What the selected layer is, in fields (§5.4 of the layers spec): its words, its geometry on the
 * layout being edited, its look and its motion. Every length is a percent of the picture's width.
 *
 * It never writes the layer itself: a changed layer goes up whole (`update`), a changed number of
 * the geometry as a patch of the layout on screen (`frame`) — the canvas decides which layout
 * that is, and copies the computer's to the phone on the phone's first change.
 */
defineOptions({ name: 'WxBannerLayerInspector' })

const props = defineProps<{
  layer: BannerLayer
  view: LayerView
  tags: { value: string; label: string }[]
  variants: { value: string; label: string }[]
  palette: { key: string; value: string }[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  update: [layer: BannerLayer]
  frame: [patch: Partial<LayerFrame>]
  'same-as-desktop': []
  delete: []
}>()

const t = useTranslate('webx-banners')
const askRemove = useRemoveConfirm()
const locales = useLocales()

const current = computed<LayerFrame>(() =>
  props.view === 'mobile'
    ? (props.layer.frames.mobile ?? props.layer.frames.desktop)
    : props.layer.frames.desktop,
)

const ownPhone = computed(() => props.view === 'mobile' && props.layer.frames.mobile !== null)

function change(mutate: (layer: BannerLayer) => void): void {
  const next = clone(props.layer)

  mutate(next)
  emit('update', next)
}

function setStyle<K extends keyof LayerStyle>(key: K, value: LayerStyle[K]): void {
  change((layer) => {
    layer.style[key] = value
  })
}

const hasWords = computed(() => props.layer.type === 'text' || props.layer.type === 'button')

const text = computed<LocalizedValue>({
  get: () => props.layer.text ?? {},
  set: (value) =>
    change((layer) => {
      layer.text = value
    }),
})

const name = computed<string>({
  get: () => props.layer.name ?? '',
  set: (value) =>
    change((layer) => {
      layer.name = value
    }),
})

/** What is on screen in the active language — and which `{…}` in it is not a field. */
const typos = computed(() => unknownFields(text.value[locales.active.value] ?? ''))
const typoList = computed(() => typos.value.map((one) => `{${one}}`).join(', '))

/**
 * The language whose words a reader of the active one sees when it is empty (decision 6), for
 * the field's hint.
 */
const fallsBackTo = computed(() => {
  const map = text.value
  const active = locales.active.value

  if ((map[active] ?? '').trim() !== '') return null

  return (
    [...locales.list.value.map((one) => one.code), ...Object.keys(map)].find(
      (code) => (map[code] ?? '').trim() !== '',
    ) ?? null
  )
})

function insert(field: 'title' | 'text'): void {
  const code = locales.active.value
  const words = text.value[code] ?? ''

  text.value = { ...text.value, [code]: `${words}${words === '' ? '' : ' '}{${field}}` }
}

const image = computed<MediaRef | null>({
  get: () => props.layer.image ?? null,
  set: (value) =>
    change((layer) => {
      layer.image = value
    }),
})

const link = computed<LinkValue | null>({
  get: () => props.layer.link ?? null,
  set: (value) =>
    change((layer) => {
      layer.link = value
    }),
})

const choice = <T,>(get: () => T, set: (value: T) => void) =>
  computed<string | number | null>({
    get: () => get() as unknown as string | number | null,
    set: (value) => set(value as unknown as T),
  })

const tag = choice(
  () => props.layer.tag ?? 'p',
  (value: string) =>
    change((layer) => {
      layer.tag = value
    }),
)

const variant = choice(
  () => props.layer.variant ?? props.variants[0]?.value ?? null,
  (value: string | null) =>
    change((layer) => {
      layer.variant = value
    }),
)

const font = choice(
  () => props.layer.style.font ?? 'site',
  (value: string) => setStyle('font', value === 'heading' || value === 'body' ? value : null),
)

const weight = choice(
  () => props.layer.style.weight,
  (value: number) => setStyle('weight', Number(value)),
)

const align = computed<string | number>({
  get: () => props.layer.style.align,
  set: (value) => setStyle('align', value as LayerStyle['align']),
})

const entrance = choice(
  () => props.layer.motion.in ?? 'none',
  (value: string) =>
    change((layer) => {
      layer.motion.in = (ENTRANCES as readonly string[]).includes(value)
        ? (value as BannerLayer['motion']['in'])
        : null
    }),
)

const loop = choice(
  () => props.layer.motion.loop ?? 'none',
  (value: string) =>
    change((layer) => {
      layer.motion.loop = (LOOPS as readonly string[]).includes(value)
        ? (value as BannerLayer['motion']['loop'])
        : null
    }),
)

type FrameNumber = keyof typeof FRAME_LIMITS

/** The numbers of the geometry this kind of layer has. */
const numbers = computed<FrameNumber[]>(() => {
  switch (props.layer.type) {
    case 'image':
      return ['x', 'y', 'w', 'radius', 'rotate']
    case 'buttons':
      return ['x', 'y', 'w', 'size', 'gap', 'rotate']
    default:
      return ['x', 'y', 'w', 'size', 'pad', 'radius', 'tracking', 'rotate']
  }
})

const steps: Record<FrameNumber, number> = {
  x: 0.05,
  y: 0.05,
  w: 0.5,
  size: 0.05,
  pad: 0.05,
  radius: 0.1,
  tracking: 0.01,
  rotate: 1,
  gap: 0.1,
}

function setNumber(key: FrameNumber, value: number | null): void {
  if (value === null || Number.isNaN(value)) return

  emit('frame', { [key]: value })
}

function setOrigin(origin: LayerOrigin): void {
  emit('frame', { origin })
}

const fontOptions = computed(() => [
  { value: 'site', label: t('layers.font-site') },
  { value: 'heading', label: t('layers.font-heading') },
  { value: 'body', label: t('layers.font-body') },
])

const weightOptions = [100, 200, 300, 400, 500, 600, 700, 800, 900].map((value) => ({
  value,
  label: String(value),
}))

const alignOptions = computed(() => [
  { value: 'start', label: t('layers.align-start') },
  { value: 'center', label: t('layers.align-center') },
  { value: 'end', label: t('layers.align-end') },
])

const entranceOptions = computed(() => [
  { value: 'none', label: t('layers.motion-none') },
  ...ENTRANCES.map((value) => ({ value, label: t(`layers.in-${value}`) })),
])

const loopOptions = computed(() => [
  { value: 'none', label: t('layers.motion-none') },
  ...LOOPS.map((value) => ({ value, label: t(`layers.loop-${value}`) })),
])

/* A colour is a swatch of the theme's palette (it follows the theme) or one of its own. */
function colorOf(key: 'color' | 'background'): string | null {
  const value = props.layer.style[key]

  if (value === null) return null
  if (value.startsWith('token:')) {
    return props.palette.find((one) => `token:${one.key}` === value)?.value ?? null
  }

  return value
}

function pickColor(key: 'color' | 'background', value: string | null): void {
  const hex =
    typeof value === 'string' && /^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test(value)
      ? value.toLowerCase()
      : null

  setStyle(key, hex)
}
</script>

<template>
  <div class="wx-banner-layer-inspector">
    <div class="wx-banner-layer-inspector__head">
      <wx-input
        v-model="name"
        size="sm"
        :placeholder="t('layers.name')"
        :aria-label="t('layers.name')"
        :disabled="disabled"
      />
      <wx-popconfirm
        :title="askRemove.confirm"
        :confirm-text="askRemove.confirmText"
        :cancel-text="askRemove.cancelText"
        confirm-type="danger"
        side="bottom"
        align="end"
        :disabled="disabled"
        @confirm="emit('delete')"
      >
        <template #trigger>
          <wx-button size="sm" variant="outline" danger :disabled="disabled">
            <template #icon><wx-icon name="trash" /></template>
            {{ t('layers.delete') }}
          </wx-button>
        </template>
      </wx-popconfirm>
    </div>

    <template v-if="hasWords">
      <wx-form-item
        :label="layer.type === 'button' ? t('layers.label') : t('layers.words')"
        :help="
          fallsBackTo
            ? t('layers.falls-back', { code: fallsBackTo.toUpperCase() })
            : t('layers.words-help')
        "
      >
        <wx-textarea
          v-if="layer.type === 'text'"
          v-model="text"
          localized
          :rows="2"
          :disabled="disabled"
        />
        <wx-input v-else v-model="text" localized :disabled="disabled" />
      </wx-form-item>
      <div class="wx-banner-layer-inspector__fields">
        <wx-button size="sm" variant="text" :disabled="disabled" @click="insert('title')"
          >+ {title}</wx-button
        >
        <wx-button size="sm" variant="text" :disabled="disabled" @click="insert('text')"
          >+ {text}</wx-button
        >
      </div>
      <wx-text v-if="typos.length > 0" size="sm" tone="warning">
        {{ t('layers.unknown-field', { fields: typoList }) }}
      </wx-text>
    </template>

    <wx-form-item v-if="layer.type === 'text'" :label="t('layers.tag')">
      <wx-select v-model="tag" :options="tags" size="sm" :disabled="disabled" />
    </wx-form-item>

    <wx-form-item v-if="layer.type === 'image'" :label="t('layers.image')">
      <wx-media-field
        v-model="image"
        accept="image"
        :aspect="null"
        :height="120"
        :disabled="disabled"
      />
    </wx-form-item>

    <template v-if="layer.type === 'button'">
      <wx-form-item :label="t('layers.link')">
        <wx-link-field v-model="link" :allow-none="false" :disabled="disabled" />
      </wx-form-item>
      <wx-form-item :label="t('layers.variant')">
        <wx-select v-model="variant" :options="variants" size="sm" :disabled="disabled" />
      </wx-form-item>
    </template>

    <wx-text v-if="layer.type === 'buttons'" size="sm" tone="muted">{{
      t('layers.buttons-help')
    }}</wx-text>

    <section class="wx-banner-layer-inspector__group">
      <div class="wx-banner-layer-inspector__title">
        <wx-text size="sm" strong>
          {{
            view === 'mobile'
              ? ownPhone
                ? t('layers.geometry-mobile-own')
                : t('layers.geometry-mobile-same')
              : t('layers.geometry-desktop')
          }}
        </wx-text>
        <wx-button
          v-if="ownPhone"
          size="sm"
          variant="text"
          :disabled="disabled"
          @click="emit('same-as-desktop')"
        >
          {{ t('layers.same-as-desktop') }}
        </wx-button>
      </div>
      <wx-text size="sm" tone="muted">{{ t('layers.unit') }}</wx-text>

      <div class="wx-banner-layer-inspector__grid">
        <wx-form-item v-for="key in numbers" :key="key" :label="t(`layers.${key}`)">
          <wx-input-number
            :model-value="current[key]"
            :min="FRAME_LIMITS[key][0]"
            :max="FRAME_LIMITS[key][1]"
            :step="steps[key]"
            :precision="2"
            size="sm"
            :disabled="disabled"
            @update:model-value="
              (value: number | null | undefined) => setNumber(key, value ?? null)
            "
          />
        </wx-form-item>
      </div>

      <wx-form-item :label="t('layers.origin')" :help="t('layers.origin-help')">
        <div
          class="wx-banner-layer-inspector__origins"
          role="radiogroup"
          :aria-label="t('layers.origin')"
        >
          <button
            v-for="origin in ORIGINS"
            :key="origin"
            type="button"
            role="radio"
            :aria-checked="current.origin === origin"
            :aria-label="origin"
            :disabled="disabled"
            :class="[
              'wx-banner-layer-inspector__origin',
              { 'is-active': current.origin === origin },
            ]"
            @click="setOrigin(origin)"
          />
        </div>
      </wx-form-item>

      <wx-switch
        :model-value="current.hidden"
        :label="t('layers.hidden-here')"
        :disabled="disabled"
        @update:model-value="(value) => emit('frame', { hidden: value === true })"
      />
    </section>

    <section v-if="layer.type !== 'image'" class="wx-banner-layer-inspector__group">
      <wx-text size="sm" strong>{{ t('layers.look') }}</wx-text>

      <div class="wx-banner-layer-inspector__grid">
        <wx-form-item :label="t('layers.font')">
          <wx-select v-model="font" :options="fontOptions" size="sm" :disabled="disabled" />
        </wx-form-item>
        <wx-form-item :label="t('layers.weight')">
          <wx-select v-model="weight" :options="weightOptions" size="sm" :disabled="disabled" />
        </wx-form-item>
        <wx-form-item :label="t('layers.line-height')">
          <wx-input-number
            :model-value="layer.style.line_height"
            :min="0.8"
            :max="3"
            :step="0.05"
            :precision="2"
            size="sm"
            :disabled="disabled"
            @update:model-value="
              (value: number | null | undefined) => value != null && setStyle('line_height', value)
            "
          />
        </wx-form-item>
        <wx-form-item :label="t('layers.opacity')">
          <wx-input-number
            :model-value="layer.style.opacity"
            :min="0"
            :max="1"
            :step="0.05"
            :precision="2"
            size="sm"
            :disabled="disabled"
            @update:model-value="
              (value: number | null | undefined) => value != null && setStyle('opacity', value)
            "
          />
        </wx-form-item>
      </div>

      <wx-form-item :label="t('layers.align')">
        <wx-segmented v-model="align" :options="alignOptions" size="sm" :disabled="disabled" />
      </wx-form-item>

      <div class="wx-banner-layer-inspector__switches">
        <wx-switch
          :model-value="layer.style.italic"
          :label="t('layers.italic')"
          :disabled="disabled"
          @update:model-value="(value) => setStyle('italic', value === true)"
        />
        <wx-switch
          :model-value="layer.style.uppercase"
          :label="t('layers.uppercase')"
          :disabled="disabled"
          @update:model-value="(value) => setStyle('uppercase', value === true)"
        />
        <wx-switch
          :model-value="layer.style.shadow"
          :label="t('layers.shadow')"
          :disabled="disabled"
          @update:model-value="(value) => setStyle('shadow', value === true)"
        />
      </div>

      <wx-form-item
        v-for="key in ['color', 'background'] as const"
        :key="key"
        :label="t(`layers.${key}`)"
      >
        <div class="wx-banner-layer-inspector__color">
          <button
            v-for="swatch in palette"
            :key="swatch.key"
            type="button"
            :title="swatch.key"
            :aria-label="swatch.key"
            :disabled="disabled"
            :class="[
              'wx-banner-layer-inspector__swatch',
              { 'is-active': layer.style[key] === `token:${swatch.key}` },
            ]"
            :style="{ background: swatch.value }"
            @click="setStyle(key, `token:${swatch.key}`)"
          />
          <wx-color-picker
            :model-value="colorOf(key)"
            clearable
            size="sm"
            :disabled="disabled"
            @update:model-value="(value: string | null) => pickColor(key, value)"
          />
        </div>
      </wx-form-item>
    </section>

    <section class="wx-banner-layer-inspector__group">
      <wx-text size="sm" strong>{{ t('layers.motion') }}</wx-text>
      <div class="wx-banner-layer-inspector__grid">
        <wx-form-item :label="t('layers.entrance')">
          <wx-select v-model="entrance" :options="entranceOptions" size="sm" :disabled="disabled" />
        </wx-form-item>
        <wx-form-item :label="t('layers.loop')">
          <wx-select v-model="loop" :options="loopOptions" size="sm" :disabled="disabled" />
        </wx-form-item>
        <wx-form-item :label="t('layers.delay')">
          <wx-input-number
            :model-value="layer.motion.delay"
            :min="0"
            :max="10000"
            :step="50"
            size="sm"
            :disabled="disabled || layer.motion.in === null"
            @update:model-value="
              (value: number | null | undefined) =>
                value != null && change((one) => (one.motion.delay = value))
            "
          />
        </wx-form-item>
        <wx-form-item :label="t('layers.duration')">
          <wx-input-number
            :model-value="layer.motion.duration"
            :min="100"
            :max="5000"
            :step="50"
            size="sm"
            :disabled="disabled || layer.motion.in === null"
            @update:model-value="
              (value: number | null | undefined) =>
                value != null && change((one) => (one.motion.duration = value))
            "
          />
        </wx-form-item>
      </div>
    </section>
  </div>
</template>

<style scoped>
.wx-banner-layer-inspector {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-banner-layer-inspector__head {
  display: flex;
  gap: var(--wx-space-8);
}

.wx-banner-layer-inspector__fields {
  display: flex;
  gap: var(--wx-space-4);
  margin-top: calc(-1 * var(--wx-space-8));
}

.wx-banner-layer-inspector__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding-top: var(--wx-space-12);
  border-top: 1px solid var(--wx-border-default);
}

.wx-banner-layer-inspector__title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-banner-layer-inspector__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 var(--wx-space-8);
}

.wx-banner-layer-inspector__grid :deep(.wx-form-item),
.wx-banner-layer-inspector :deep(.wx-form-item) {
  margin-bottom: var(--wx-space-4);
}

.wx-banner-layer-inspector__switches {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
}

.wx-banner-layer-inspector__origins {
  display: grid;
  grid-template-columns: repeat(3, 18px);
  gap: var(--wx-space-4);
}

.wx-banner-layer-inspector__origin {
  width: 18px;
  height: 18px;
  padding: 0;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-surface);
  cursor: pointer;
}

.wx-banner-layer-inspector__origin.is-active {
  border-color: var(--wx-color-primary);
  background: var(--wx-color-primary);
}

.wx-banner-layer-inspector__color {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
}

.wx-banner-layer-inspector__swatch {
  width: 22px;
  height: 22px;
  padding: 0;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-full);
  cursor: pointer;
}

.wx-banner-layer-inspector__swatch.is-active {
  outline: 2px solid var(--wx-color-primary);
  outline-offset: 1px;
}
</style>
