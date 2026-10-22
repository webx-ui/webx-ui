<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import {
  useLocales,
  WxButton,
  WxIcon,
  WxSegmented,
  WxSortableList,
  WxText,
  WxTooltip,
  WxPopconfirm,
} from '@webx-ui/core'
import { useAdmin, useRemoveConfirm, useTranslate } from '@webx-ui/module-admin'
import { createMediaApi } from '@webx-ui/module-media'
import { createBannersApi } from './api'
import { useBannerEditor } from './editor'
import { useBannersMessages } from './i18n'
import LayerInspector from './LayerInspector.vue'
import {
  clampFrame,
  clone,
  cropBox,
  layerClasses,
  layerVars,
  layerWords,
  newLayer,
  ratioOf,
  type BannerLayer,
  type LayerFrame,
  type LayerType,
  type LayerView,
} from './layers'
import type { BannerButton, BannersTheme } from './types'
import '../../../php/packages/module-banners/resources/css/layers.css'

/**
 * The layers of a banner, edited on its picture (§5 of the layers spec).
 *
 * The canvas is the site: the same `layers.css`, the same custom properties the server prints,
 * every length a percent of the picture's width — so the width of this column changes the scale
 * and nothing else. It shows the whole picture and darkens what the frame of the place crops
 * away; a layer under the shade is not seen on the site.
 *
 * A field of `banners.form` like any other: the value is the list of layers, and every change is
 * a new list. Undo is this field's own — Ctrl+Z on the canvas — because a text input keeps its
 * own and the two must not fight.
 */
defineOptions({ name: 'WxBannerLayers' })

const props = defineProps<{
  modelValue?: BannerLayer[] | null
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: BannerLayer[]] }>()

const admin = useAdmin()
const editor = useBannerEditor()
const locales = useLocales()
const media = createMediaApi(admin)
const api = createBannersApi(admin)
useBannersMessages()

const t = useTranslate('webx-banners')
const askRemove = useRemoveConfirm()

const layers = computed<BannerLayer[]>(() => props.modelValue ?? [])
const place = computed(() => editor?.place.value?.layers ?? null)

const view = ref<LayerView>('desktop')
const selectedId = ref<string | null>(null)
const selected = computed(() => layers.value.find((layer) => layer.id === selectedId.value) ?? null)

const locale = computed(() => {
  const active = locales.active.value

  return locales.list.value.some((one) => one.code === active)
    ? active
    : (locales.list.value[0]?.code ?? 'en')
})
const order = computed(() => locales.list.value.map((one) => one.code))

/* --- History ---------------------------------------------------------------------------- */

const past = ref<string[]>([])
const future = ref<string[]>([])

function remember(): void {
  past.value = [...past.value.slice(-99), JSON.stringify(layers.value)]
  future.value = []
}

function commit(next: BannerLayer[], record = true): void {
  if (record) remember()

  emit('update:modelValue', next)
}

function undo(): void {
  const last = past.value.at(-1)

  if (last === undefined) return

  future.value = [...future.value, JSON.stringify(layers.value)]
  past.value = past.value.slice(0, -1)
  emit('update:modelValue', JSON.parse(last) as BannerLayer[])
}

function redo(): void {
  const next = future.value.at(-1)

  if (next === undefined) return

  past.value = [...past.value, JSON.stringify(layers.value)]
  future.value = future.value.slice(0, -1)
  emit('update:modelValue', JSON.parse(next) as BannerLayer[])
}

/* --- Changing layers --------------------------------------------------------------------- */

function replace(id: string, change: (layer: BannerLayer) => BannerLayer, record = true): void {
  commit(
    layers.value.map((layer) => (layer.id === id ? change(clone(layer)) : layer)),
    record,
  )
}

/** The layout being edited; the phone's is the computer's until it is changed (decision 3). */
function frameOf(layer: BannerLayer, which: LayerView = view.value): LayerFrame {
  return which === 'mobile' ? (layer.frames.mobile ?? layer.frames.desktop) : layer.frames.desktop
}

function setFrame(id: string, patch: Partial<LayerFrame>, record = true): void {
  replace(
    id,
    (layer) => {
      const next = clampFrame({ ...frameOf(layer), ...patch })

      if (view.value === 'mobile') layer.frames.mobile = next
      else layer.frames.desktop = next

      return layer
    },
    record,
  )
}

/* Inspector edits typed in a row are one step of history, not one per key. */
let lastEdit = { id: '', at: 0 }

function edit(next: BannerLayer): void {
  const now = Date.now()
  const merge = lastEdit.id === next.id && now - lastEdit.at < 800

  lastEdit = { id: next.id, at: now }
  replace(next.id, () => next, !merge)
}

function add(type: LayerType): void {
  const layer = newLayer(
    type,
    layers.value.map((one) => one.id),
    locale.value,
  )

  commit([layer, ...layers.value])
  selectedId.value = layer.id
}

function remove(id: string): void {
  commit(layers.value.filter((layer) => layer.id !== id))

  if (selectedId.value === id) selectedId.value = null
}

function duplicate(id: string): void {
  const index = layers.value.findIndex((layer) => layer.id === id)
  const source = layers.value[index]

  if (!source || source.type === 'buttons') return

  const copy = clone(source)

  copy.id = newLayer(
    'text',
    layers.value.map((one) => one.id),
    locale.value,
  ).id
  copy.frames.desktop = {
    ...copy.frames.desktop,
    x: copy.frames.desktop.x + 2,
    y: copy.frames.desktop.y + 2,
  }

  const next = [...layers.value]

  next.splice(index, 0, copy)
  commit(next)
  selectedId.value = copy.id
}

function toggleHidden(layer: BannerLayer): void {
  setFrame(layer.id, { hidden: !frameOf(layer).hidden })
}

const hasButtonsLayer = computed(() => layers.value.some((layer) => layer.type === 'buttons'))

const listModel = computed<BannerLayer[]>({
  get: () => layers.value,
  set: (next) => commit(next),
})

/* --- The pictures ------------------------------------------------------------------------ */

interface Picture {
  url: string
  ratio: number | null
}

const pictures = reactive<Record<string, Picture | null>>({})

function pathOf(value: unknown): string | null {
  const path = (value as { path?: unknown } | null)?.path

  return typeof path === 'string' && path !== '' ? path : null
}

async function resolve(path: string | null): Promise<void> {
  if (path === null || path in pictures) return

  pictures[path] = null

  try {
    const file = await media.fileByPath(path)

    pictures[path] = file
      ? { url: file.url, ratio: file.width && file.height ? file.width / file.height : null }
      : null
  } catch {
    pictures[path] = null
  }
}

const desktopPath = computed(() => pathOf(editor?.values.value.image))
const mobilePath = computed(() => pathOf(editor?.values.value.image_mobile))

watch(
  () => [desktopPath.value, mobilePath.value, ...layers.value.map((layer) => pathOf(layer.image))],
  (paths) => paths.forEach((path) => void resolve(path)),
  { immediate: true },
)

const frameRatio = computed(() =>
  view.value === 'mobile'
    ? ratioOf(place.value?.ratio_mobile, 0.8)
    : ratioOf(place.value?.ratio, 16 / 6),
)

const background = computed<Picture | null>(() => {
  const mobile = mobilePath.value === null ? null : (pictures[mobilePath.value] ?? null)
  const desktop = desktopPath.value === null ? null : (pictures[desktopPath.value] ?? null)

  return view.value === 'mobile' ? (mobile ?? desktop) : desktop
})

/* The canvas shows the whole picture: its own proportions, the frame's crop drawn over it. */
const imageRatio = computed(() => background.value?.ratio ?? frameRatio.value)
const crop = computed(() => cropBox(imageRatio.value, frameRatio.value))

const frameStyle = computed(() => {
  const ratio = String(imageRatio.value)

  return { '--ratio': ratio, '--ratio-mobile': ratio, '--img': ratio, '--img-m': ratio }
})

/* --- What a layer shows ------------------------------------------------------------------ */

/* Exactly the language asked for: a field with no words in it puts in nothing, as on the site. */
const ownFields = computed(() => {
  const own = (field: 'title' | 'text'): string => {
    const value = editor?.values.value[field] as Record<string, string> | undefined

    return (value?.[locale.value] ?? '').trim()
  }

  return { title: own('title'), text: own('text') }
})

function wordsOf(layer: BannerLayer): string {
  return layerWords(layer.text, locale.value, order.value, ownFields.value)
}

const bannerButtons = computed(() =>
  ((editor?.values.value.buttons as BannerButton[] | undefined) ?? [])
    .map((button) => ({
      label: (button.label?.[locale.value] ?? '').trim(),
      variant: button.variant ?? place.value?.variants[0]?.value ?? 'primary',
    }))
    .filter((button) => button.label !== ''),
)

function tagOf(layer: BannerLayer): string {
  return layer.type === 'text' && /^(p|span|h[1-6])$/.test(layer.tag ?? '')
    ? (layer.tag as string)
    : 'div'
}

function labelOf(layer: BannerLayer): string {
  if (layer.name) return layer.name

  const own = layer.text?.[locale.value] ?? Object.values(layer.text ?? {})[0] ?? ''

  if (own.trim() === '{title}') return t('layers.title-layer')
  if (own.trim() === '{text}') return t('layers.text-layer')
  if (layer.type === 'buttons') return t('layers.buttons-layer')
  if (layer.type === 'image') return t('layers.image')

  return own.trim() || t(`layers.${layer.type}`)
}

const icons: Record<LayerType, string> = {
  text: 'file-text',
  image: 'image',
  button: 'link',
  buttons: 'grid',
}

/** A layer the frame hides on this layout: wholly under the shade. */
function outside(layer: BannerLayer): boolean {
  const one = frameOf(layer)
  const height = 100 / imageRatio.value
  const box = crop.value
  const left = box.left
  const right = box.left + box.width
  const top = (box.top / 100) * height
  const bottom = ((box.top + box.height) / 100) * height

  // The box itself, not its anchor: a layer anchored on the right reaches left of its x.
  const x = one.x + (originShift(one.origin)[0] / 100) * one.w

  return x > right || x + one.w < left || one.y > bottom || one.y < top - 50
}

/* --- Pointer: move and resize ------------------------------------------------------------ */

const stage = ref<HTMLElement | null>(null)
const canvas = ref<HTMLElement | null>(null)
const guides = ref<{ axis: 'x' | 'y'; at: number }[]>([])

/** How far the pointer must go before a press becomes a drag, in px. */
const SLOP = 3
/** How close an edge must come to a guide to stick to it, in px. */
const SNAP = 6

function snapTargets(axis: 'x' | 'y', skip: string): number[] {
  const height = 100 / imageRatio.value
  const box = crop.value
  const targets =
    axis === 'x'
      ? [0, 50, 100, box.left, box.left + box.width]
      : [0, height / 2, height, (box.top / 100) * height, ((box.top + box.height) / 100) * height]

  for (const layer of layers.value) {
    if (layer.id === skip) continue

    const one = frameOf(layer)

    if (axis === 'x') targets.push(one.x, one.x + one.w)
    else targets.push(one.y)
  }

  return targets
}

function snap(
  axis: 'x' | 'y',
  value: number,
  size: number,
  unit: number,
  skip: string,
  free: boolean,
): number {
  if (free) return value

  const edges = [0, size / 2, size]
  let best: { delta: number; at: number } | null = null

  for (const target of snapTargets(axis, skip)) {
    for (const edge of edges) {
      const delta = target - (value + edge)

      if (
        Math.abs(delta) * unit <= SNAP &&
        (best === null || Math.abs(delta) < Math.abs(best.delta))
      ) {
        best = { delta, at: target }
      }
    }
  }

  if (best === null) return value

  guides.value = [...guides.value.filter((one) => one.axis !== axis), { axis, at: best.at }]

  return value + best.delta
}

function select(layer: BannerLayer): void {
  selectedId.value = layer.id
  canvas.value?.focus({ preventScroll: true })
}

function press(event: PointerEvent, layer: BannerLayer, mode: 'move' | 'width' | 'scale'): void {
  select(layer)

  if (props.disabled || event.button !== 0 || !stage.value) return

  event.preventDefault()

  const rect = stage.value.getBoundingClientRect()
  const unit = rect.width / 100
  const element = (event.target as HTMLElement).closest('.webx-banner__layer') as HTMLElement | null
  const box = element?.getBoundingClientRect()
  const height = box ? box.height / unit : 0
  const from = { ...frameOf(layer) }
  const start = { x: event.clientX, y: event.clientY }
  let moving = false

  const move = (next: PointerEvent): void => {
    const dx = (next.clientX - start.x) / unit
    const dy = (next.clientY - start.y) / unit

    if (!moving) {
      if (Math.hypot(next.clientX - start.x, next.clientY - start.y) < SLOP) return

      moving = true
      remember()
    }

    guides.value = []

    if (mode === 'move') {
      // The box's own left and top, so its edges and its middle are what sticks.
      const shift = {
        x: (originShift(from.origin)[0] / 100) * from.w,
        y: (originShift(from.origin)[1] / 100) * height,
      }
      const left = snap('x', from.x + dx + shift.x, from.w, unit, layer.id, next.altKey)
      const top = snap('y', from.y + dy + shift.y, height, unit, layer.id, next.altKey)

      setFrame(layer.id, { x: left - shift.x, y: top - shift.y }, false)
    } else if (mode === 'width') {
      setFrame(layer.id, { w: Math.max(1, from.w + dx) }, false)
    } else {
      const factor = Math.max(0.05, (from.w + dx) / from.w)

      setFrame(layer.id, { w: from.w * factor, size: from.size * factor }, false)
    }
  }

  const up = (): void => {
    window.removeEventListener('pointermove', move)
    window.removeEventListener('pointerup', up)
    window.removeEventListener('pointercancel', up)
    guides.value = []
  }

  window.addEventListener('pointermove', move)
  window.addEventListener('pointerup', up)
  window.addEventListener('pointercancel', up)
}

function originShift(origin: LayerFrame['origin']): [number, number] {
  const x = origin.endsWith('r')
    ? -100
    : origin === 't' || origin === 'c' || origin === 'b'
      ? -50
      : 0
  const y = origin.startsWith('b')
    ? -100
    : origin === 'l' || origin === 'c' || origin === 'r'
      ? -50
      : 0

  return [x, y]
}

function pressCanvas(event: PointerEvent): void {
  if (!(event.target as HTMLElement).closest('.webx-banner__layer')) selectedId.value = null
}

/* --- Keyboard ---------------------------------------------------------------------------- */

function onKeydown(event: KeyboardEvent): void {
  const mod = event.ctrlKey || event.metaKey
  const key = event.key.toLowerCase()

  if (mod && key === 'z') {
    event.preventDefault()

    if (event.shiftKey) redo()
    else undo()

    return
  }

  if (mod && key === 'y') {
    event.preventDefault()
    redo()

    return
  }

  const layer = selected.value

  if (!layer || props.disabled) return

  if (mod && key === 'd') {
    event.preventDefault()
    duplicate(layer.id)

    return
  }

  if (key === 'delete' || key === 'backspace') {
    event.preventDefault()
    remove(layer.id)

    return
  }

  if (key === 'escape') {
    selectedId.value = null

    return
  }

  const step = event.shiftKey ? 0.5 : 0.05
  const arrows: Record<string, [number, number]> = {
    arrowleft: [-step, 0],
    arrowright: [step, 0],
    arrowup: [0, -step],
    arrowdown: [0, step],
  }
  const by = arrows[key]

  if (!by) return

  event.preventDefault()

  const one = frameOf(layer)
  const now = Date.now()
  // A key held down is one step of history.
  const merge = lastEdit.id === `arrows:${layer.id}` && now - lastEdit.at < 800

  lastEdit = { id: `arrows:${layer.id}`, at: now }
  setFrame(layer.id, { x: one.x + by[0], y: one.y + by[1] }, !merge)
}

/* --- The theme --------------------------------------------------------------------------- */

const theme = ref<BannersTheme | null>(null)
let themeStyle: HTMLStyleElement | null = null

onMounted(async () => {
  try {
    theme.value = await api.theme()
  } catch {
    theme.value = null
  }

  const css = `${theme.value?.tokens_css ?? ''}\n${theme.value?.fonts_css ?? ''}`.trim()

  if (css !== '' && !document.getElementById('wx-banners-theme')) {
    themeStyle = document.createElement('style')
    themeStyle.id = 'wx-banners-theme'
    themeStyle.textContent = css
    document.head.append(themeStyle)
  }
})

onBeforeUnmount(() => {
  themeStyle?.remove()
})

const viewOptions = computed(() => [
  { value: 'desktop', label: t('layers.desktop'), icon: 'monitor' as const },
  { value: 'mobile', label: t('layers.mobile'), icon: 'smartphone' as const },
])

const viewModel = computed<string | number>({
  get: () => view.value,
  set: (value) => {
    view.value = value === 'mobile' ? 'mobile' : 'desktop'
  },
})
</script>

<template>
  <div class="wx-banner-layers" :class="{ 'is-disabled': disabled }">
    <div class="wx-banner-layers__bar">
      <wx-segmented
        v-model="viewModel"
        :options="viewOptions"
        size="sm"
        :aria-label="t('layers.layout')"
      />

      <div class="wx-banner-layers__tools">
        <wx-tooltip :content="t('layers.undo')">
          <wx-button
            size="sm"
            variant="text"
            :disabled="disabled || past.length === 0"
            :aria-label="t('layers.undo')"
            @click="undo"
          >
            <template #icon><wx-icon name="rotate-left" /></template>
          </wx-button>
        </wx-tooltip>
        <wx-tooltip :content="t('layers.redo')">
          <wx-button
            size="sm"
            variant="text"
            :disabled="disabled || future.length === 0"
            :aria-label="t('layers.redo')"
            @click="redo"
          >
            <template #icon><wx-icon name="rotate-right" /></template>
          </wx-button>
        </wx-tooltip>
        <wx-button size="sm" variant="outline" :disabled="disabled" @click="add('text')">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('layers.text') }}
        </wx-button>
        <wx-button size="sm" variant="outline" :disabled="disabled" @click="add('image')">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('layers.image') }}
        </wx-button>
        <wx-button size="sm" variant="outline" :disabled="disabled" @click="add('button')">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('layers.button') }}
        </wx-button>
        <wx-button
          v-if="!hasButtonsLayer"
          size="sm"
          variant="outline"
          :disabled="disabled"
          @click="add('buttons')"
        >
          <template #icon><wx-icon name="plus" /></template>
          {{ t('layers.buttons-layer') }}
        </wx-button>
      </div>
    </div>

    <wx-text v-if="!place" size="sm" tone="muted">{{ t('layers.not-here') }}</wx-text>

    <div class="wx-banner-layers__body">
      <div class="wx-banner-layers__main">
        <div
          ref="canvas"
          class="wx-banner-layers__canvas"
          :class="{ 'is-overflow': place?.overflow }"
          tabindex="0"
          :aria-label="t('layers.canvas')"
          @pointerdown="pressCanvas"
          @keydown="onKeydown"
        >
          <div
            class="webx-banner wx-banner-layers__frame"
            :class="{ 'is-mobile': view === 'mobile' }"
            :style="frameStyle"
          >
            <div class="webx-banner__picture">
              <img v-if="background" class="webx-banner__image" :src="background.url" alt="" />
              <div v-else class="wx-banner-layers__empty">{{ t('layers.no-picture') }}</div>
            </div>

            <div ref="stage" class="webx-banner__stage">
              <component
                :is="tagOf(layer)"
                v-for="(layer, index) in layers"
                :key="layer.id"
                :class="[
                  ...layerClasses(layer),
                  { 'is-selected': layer.id === selectedId, 'is-outside': outside(layer) },
                ]"
                :style="layerVars(layer, layers.length - index)"
                @pointerdown.stop="press($event, layer, 'move')"
              >
                <span
                  v-if="layer.type === 'text'"
                  class="webx-banner__inner wx-banner-layers__words"
                  >{{ wordsOf(layer) || '…' }}</span
                >
                <img
                  v-else-if="layer.type === 'image'"
                  class="webx-banner__inner"
                  :src="pictures[pathOf(layer.image) ?? '']?.url ?? ''"
                  alt=""
                  draggable="false"
                />
                <span
                  v-else-if="layer.type === 'button'"
                  class="webx-banner__inner webx-banner__buttons"
                >
                  <span
                    :class="[
                      'webx-banner__button',
                      `webx-banner__button--${layer.variant ?? place?.variants[0]?.value ?? 'primary'}`,
                    ]"
                  >
                    {{ wordsOf(layer) || '…' }}
                  </span>
                </span>
                <span v-else class="webx-banner__inner webx-banner__buttons">
                  <span
                    v-for="(button, at) in bannerButtons"
                    :key="at"
                    :class="['webx-banner__button', `webx-banner__button--${button.variant}`]"
                    >{{ button.label }}</span
                  >
                  <span v-if="bannerButtons.length === 0" class="webx-banner__button">{{
                    t('layers.buttons-layer')
                  }}</span>
                </span>

                <template v-if="layer.id === selectedId && !disabled">
                  <span
                    class="wx-banner-layers__handle wx-banner-layers__handle--width"
                    @pointerdown.stop="press($event, layer, 'width')"
                  />
                  <span
                    class="wx-banner-layers__handle wx-banner-layers__handle--scale"
                    @pointerdown.stop="press($event, layer, 'scale')"
                  />
                </template>
              </component>
            </div>

            <div
              class="wx-banner-layers__crop"
              :style="{
                left: `${crop.left}%`,
                top: `${crop.top}%`,
                width: `${crop.width}%`,
                height: `${crop.height}%`,
              }"
            />

            <div
              v-for="guide in guides"
              :key="guide.axis"
              :class="['wx-banner-layers__guide', `wx-banner-layers__guide--${guide.axis}`]"
              :style="
                guide.axis === 'x' ? { left: `${guide.at}%` } : { top: `calc(${guide.at} * 1cqw)` }
              "
            />
          </div>
        </div>

        <wx-text size="sm" tone="muted">{{ t('layers.hint') }}</wx-text>

        <wx-sortable-list
          v-model="listModel"
          class="wx-banner-layers__list"
          item-key="id"
          size="sm"
          :disabled="disabled"
          :empty-text="t('layers.empty')"
          :aria-label="t('layers.list')"
        >
          <template #default="{ item }">
            <button
              type="button"
              class="wx-banner-layers__row"
              :class="{ 'is-selected': (item as BannerLayer).id === selectedId }"
              @click="select(item as BannerLayer)"
            >
              <wx-icon :name="icons[(item as BannerLayer).type] as 'image'" />
              <span class="wx-banner-layers__name">{{ labelOf(item as BannerLayer) }}</span>
              <wx-icon
                v-if="outside(item as BannerLayer)"
                name="warning"
                class="wx-banner-layers__warn"
                :aria-label="t('layers.outside')"
              />
            </button>
          </template>
          <template #actions="{ item }">
            <wx-button
              size="sm"
              variant="text"
              :disabled="disabled"
              :aria-label="
                frameOf(item as BannerLayer).hidden ? t('layers.show') : t('layers.hide')
              "
              @click="toggleHidden(item as BannerLayer)"
            >
              <template #icon>
                <wx-icon :name="frameOf(item as BannerLayer).hidden ? 'eye-off' : 'eye'" />
              </template>
            </wx-button>
            <wx-popconfirm
              :title="askRemove.confirm"
              :confirm-text="askRemove.confirmText"
              :cancel-text="askRemove.cancelText"
              confirm-type="danger"
              :disabled="disabled"
              @confirm="remove((item as BannerLayer).id)"
            >
              <template #trigger>
                <wx-button
                  size="sm"
                  variant="text"
                  :disabled="disabled"
                  :aria-label="t('layers.delete')"
                >
                  <template #icon><wx-icon name="trash" /></template>
                </wx-button>
              </template>
            </wx-popconfirm>
          </template>
        </wx-sortable-list>
      </div>

      <layer-inspector
        v-if="selected"
        class="wx-banner-layers__inspector"
        :layer="selected"
        :view="view"
        :tags="place?.tags ?? []"
        :variants="place?.variants ?? []"
        :palette="theme?.palette ?? []"
        :disabled="disabled"
        @update="edit"
        @frame="(patch: Partial<LayerFrame>) => setFrame(selected!.id, patch)"
        @same-as-desktop="
          replace(selected!.id, (layer) => ({
            ...layer,
            frames: { ...layer.frames, mobile: null },
          }))
        "
        @delete="remove(selected!.id)"
      />
      <wx-text v-else class="wx-banner-layers__inspector" size="sm" tone="muted">
        {{ t('layers.choose') }}
      </wx-text>
    </div>
  </div>
</template>

<style scoped>
.wx-banner-layers {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  container-type: inline-size;
  min-width: 0;
}

.wx-banner-layers__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-banner-layers__tools {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-banner-layers__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-16);
}

@container (min-width: 900px) {
  .wx-banner-layers__body {
    grid-template-columns: minmax(0, 1fr) 320px;
    align-items: start;
  }
}

.wx-banner-layers__main {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-banner-layers__canvas {
  position: relative;
  /* The layers, the shade and the guides stack inside the canvas, never over the sticky bar. */
  isolation: isolate;
  overflow: hidden;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-muted, var(--wx-bg-surface));
  outline-offset: 2px;
  user-select: none;
  touch-action: none;
}

/* Room below the picture for a layer that steps out of the frame (decision 13). */
.wx-banner-layers__canvas.is-overflow {
  padding-bottom: 24%;
  background-image: repeating-linear-gradient(
    45deg,
    transparent 0 8px,
    color-mix(in srgb, var(--wx-text-muted) 12%, transparent) 8px 9px
  );
}

.wx-banner-layers__canvas:focus-visible {
  outline: 2px solid var(--wx-color-primary);
}

.wx-banner-layers__frame {
  overflow: visible;
}

.wx-banner-layers__frame :deep(.webx-banner__stage) {
  pointer-events: auto;
}

/* The site's motion is the site's: the canvas stands still, so a layer is where it ends up. */
.wx-banner-layers__frame :deep(.webx-banner__layer),
.wx-banner-layers__frame :deep(.webx-banner__inner) {
  animation: none !important;
  cursor: move;
  pointer-events: auto;
}

.wx-banner-layers__words {
  white-space: pre-line;
}

.wx-banner-layers__frame :deep(.webx-banner__layer.is-selected) {
  outline: 2px solid var(--wx-color-primary);
  outline-offset: 1px;
}

.wx-banner-layers__frame :deep(.webx-banner__layer:hover:not(.is-selected)) {
  outline: 1px dashed var(--wx-color-primary);
}

.wx-banner-layers__frame :deep(.webx-banner__layer.is-outside) {
  opacity: 0.5;
}

.wx-banner-layers__handle {
  position: absolute;
  width: 10px;
  height: 10px;
  border: 2px solid var(--wx-color-primary);
  border-radius: 2px;
  background: var(--wx-bg-surface);
  translate: 50% -50%;
}

.wx-banner-layers__handle--width {
  top: 50%;
  right: 0;
  cursor: ew-resize;
}

.wx-banner-layers__handle--scale {
  right: 0;
  bottom: 0;
  translate: 50% 50%;
  cursor: nwse-resize;
}

/* What the frame of the place crops away is shaded: the site never shows it. */
.wx-banner-layers__crop {
  position: absolute;
  z-index: 1000;
  box-shadow: 0 0 0 100vmax rgb(0 0 0 / 0.45);
  outline: 1px dashed rgb(255 255 255 / 0.8);
  pointer-events: none;
}

.wx-banner-layers__guide {
  position: absolute;
  z-index: 1001;
  background: var(--wx-color-primary);
  pointer-events: none;
}

.wx-banner-layers__guide--x {
  top: 0;
  bottom: 0;
  width: 1px;
}

.wx-banner-layers__guide--y {
  left: 0;
  right: 0;
  height: 1px;
}

.wx-banner-layers__empty {
  display: grid;
  place-items: center;
  height: 100%;
  color: var(--wx-text-muted);
}

.wx-banner-layers__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
  min-width: 0;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-banner-layers__row.is-selected {
  color: var(--wx-color-primary);
  font-weight: 600;
}

.wx-banner-layers__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-banner-layers__warn {
  color: var(--wx-color-warning);
}
</style>
