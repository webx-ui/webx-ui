<script setup lang="ts">
import { computed, onBeforeUnmount, ref, shallowRef, useTemplateRef, watch } from 'vue'
import WxAction from '../Action/Action.vue'
import WxButton from '../Button/Button.vue'
import WxIcon from '../Icon/Icon.vue'
import WxInputNumber from '../InputNumber/InputNumber.vue'
import WxSegmented from '../Segmented/Segmented.vue'
import WxTooltip from '../Tooltip/Tooltip.vue'
import {
  clamp,
  fitInside,
  fitRatio,
  fullCrop,
  mirrorCrop,
  moveCrop,
  ratioLabelFor,
  resizeCrop,
  turnCrop,
  type CropHandle,
} from './crop'
import type {
  ImageEditorCrop,
  ImageEditorEmits,
  ImageEditorProps,
  ImageEditorRatio,
  ImageEditorRatioOption,
  ImageEditorResult,
} from './types'

defineOptions({ name: 'WxImageEditor' })

/*
 * A picture, a rectangle over it, and a blob at the end.
 *
 * The editing is all geometry — a crop, quarter turns, mirrorings, an output size — and
 * none of it touches the picture until Save is pressed. Then the whole of it is one
 * `drawImage` onto a canvas the size of the result: one resampling rather than a chain of
 * them, which is what keeps a photograph from going soft on the way through.
 *
 * The component uploads nothing. It answers with a blob, and whatever opened it decides
 * where that goes.
 */
const props = withDefaults(defineProps<ImageEditorProps>(), {
  fileName: undefined,
  aspect: undefined,
  ratios: undefined,
  ratio: undefined,
  rotatable: true,
  flippable: true,
  resizable: true,
  maxWidth: undefined,
  maxHeight: undefined,
  minSize: 16,
  format: 'auto',
  quality: 0.92,
  background: '#ffffff',
  crossOrigin: 'anonymous',
  footer: true,
  disabled: false,
  saveLabel: 'Save',
  cancelLabel: 'Cancel',
  resetLabel: 'Reset',
  rotateLeftLabel: 'Turn left',
  rotateRightLabel: 'Turn right',
  flipHorizontalLabel: 'Mirror across',
  flipVerticalLabel: 'Mirror down',
  ratioLabel: 'Ratio',
  cropLabel: 'Crop',
  outputLabel: 'Output',
  outputHint: 'The size of the picture you will get',
  widthLabel: 'Width',
  heightLabel: 'Height',
  freeLabel: 'Free',
  originalLabel: 'Original',
  errorText: 'This picture could not be loaded',
})

const emit = defineEmits<ImageEditorEmits>()

/* ------------------------------------------------------------------- source --- */

/*
 * A `File` from an upload field is read through an object URL, which has to be given
 * back — and only ours: a URL that came in as a string belongs to the caller.
 */
const objectUrl = ref('')

const source = computed(() => (typeof props.src === 'string' ? props.src : objectUrl.value))

onBeforeUnmount(() => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})

/* -------------------------------------------------------------------- state --- */

const image = useTemplateRef<HTMLImageElement>('image')
const stage = useTemplateRef<HTMLElement>('stage')

const natural = ref({ width: 0, height: 0 })
const ready = ref(false)
const failed = ref(false)
const busy = ref(false)

const rotation = ref(0)
const flipX = ref(false)
const flipY = ref(false)

/** The crop, in the pixels of the turned picture. */
const crop = ref<ImageEditorCrop>({ x: 0, y: 0, width: 0, height: 0 })

/** Set by hand in the width field; until then the output is the crop's own size. */
const widthOverride = ref<number | null>(null)

/** The picture as it is being looked at: sides swapped by an odd number of turns. */
const work = computed(() =>
  rotation.value % 180 === 0
    ? { width: natural.value.width, height: natural.value.height }
    : { width: natural.value.height, height: natural.value.width },
)

/* -------------------------------------------------------------------- ratio --- */

const options = computed<ImageEditorRatioOption[]>(() => {
  const given =
    props.ratios ??
    (props.aspect === undefined
      ? (['free', 'original', 1, 4 / 3, 3 / 2, 16 / 9] as ImageEditorRatio[])
      : [])

  return given.map((entry) => {
    const option = typeof entry === 'object' ? entry : { value: entry }
    if (option.label) return option
    if (option.value === 'free') return { ...option, label: props.freeLabel }
    if (option.value === 'original') return { ...option, label: props.originalLabel }
    return { ...option, label: ratioLabelFor(option.value as number) }
  })
})

const chosen = ref<ImageEditorRatio>('free')

/** Whether anything has been done to the picture — all Reset has to know. */
const touched = ref(false)

/** The number the crop is actually held to, if any. `aspect` overrules the picker. */
const ratio = computed(() => {
  if (props.aspect) return props.aspect
  if (chosen.value === 'free') return undefined
  if (chosen.value === 'original') {
    return work.value.height ? work.value.width / work.value.height : undefined
  }
  return chosen.value
})

/* Takes what the picker hands back — a string or a number — which is every ratio there is. */
function chooseRatio(value: string | number | undefined) {
  if (value === undefined) return
  chosen.value = value as ImageEditorRatio
  touched.value = true
  if (ratio.value) setCrop(fitRatio(crop.value, ratio.value, work.value))
}

/* ------------------------------------------------------------------ loading --- */

function reset({ loaded = true } = {}) {
  rotation.value = 0
  flipX.value = false
  flipY.value = false
  widthOverride.value = null
  chosen.value = props.ratio ?? options.value[0]?.value ?? 'free'
  failed.value = false
  touched.value = false

  if (!loaded) {
    ready.value = false
    natural.value = { width: 0, height: 0 }
    crop.value = { x: 0, y: 0, width: 0, height: 0 }
    return
  }

  crop.value = fullCrop(work.value, ratio.value)
}

/* Below the state it clears, and immediate: a new picture is a new everything. */
watch(
  () => props.src,
  (src) => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
    objectUrl.value = typeof src === 'string' ? '' : URL.createObjectURL(src)
    reset({ loaded: false })
  },
  { immediate: true },
)

function onLoad() {
  const el = image.value
  if (!el) return

  natural.value = { width: el.naturalWidth, height: el.naturalHeight }
  ready.value = true
  failed.value = false
  reset()
  emit('load', { ...natural.value })
}

function onError() {
  ready.value = false
  failed.value = true
  emit('error', new Error(`The picture at ${source.value} could not be loaded`))
}

/* ------------------------------------------------------------------ the view --- */

/*
 * The picture is fitted into the stage and everything on screen is that one number away
 * from the pixels underneath. Nothing is resampled to show it: the `<img>` is the browser's
 * own, turned by a CSS transform, and the crop is a box over it.
 */
const box = ref({ width: 0, height: 0 })

let observer: ResizeObserver | undefined

watch(stage, (el) => {
  observer?.disconnect()
  if (!el || typeof ResizeObserver === 'undefined') return

  observer = new ResizeObserver(() => {
    box.value = { width: el.clientWidth, height: el.clientHeight }
  })
  observer.observe(el)
  box.value = { width: el.clientWidth, height: el.clientHeight }
})

onBeforeUnmount(() => observer?.disconnect())

const scale = computed(() => {
  const { width, height } = work.value
  if (!width || !height || !box.value.width || !box.value.height) return 0
  return Math.min(box.value.width / width, box.value.height / height)
})

const frameStyle = computed(() => ({
  width: `${work.value.width * scale.value}px`,
  height: `${work.value.height * scale.value}px`,
}))

/*
 * Read right to left, the way CSS applies them: the picture is turned, then mirrored,
 * then put in the middle. The mirroring comes after the turn so that "mirror across"
 * means across the screen, whichever way up the picture currently is — and the canvas
 * composes the transform in exactly this order when it comes to writing the result.
 */
const imageStyle = computed(() => ({
  width: `${natural.value.width * scale.value}px`,
  height: `${natural.value.height * scale.value}px`,
  transform: `translate(-50%, -50%) scale(${flipX.value ? -1 : 1}, ${flipY.value ? -1 : 1}) rotate(${rotation.value}deg)`,
}))

const cropStyle = computed(() => ({
  left: `${crop.value.x * scale.value}px`,
  top: `${crop.value.y * scale.value}px`,
  width: `${crop.value.width * scale.value}px`,
  height: `${crop.value.height * scale.value}px`,
}))

/* --------------------------------------------------------------- the gesture --- */

const handles: CropHandle[] = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']

const frame = useTemplateRef<HTMLElement>('frame')
const cropBox = useTemplateRef<HTMLElement>('cropBox')

interface Gesture {
  handle: CropHandle | 'move'
  start: ImageEditorCrop
  from: { x: number; y: number }
}

const gesture = shallowRef<Gesture | null>(null)

/** Where in the picture's own pixels the pointer is. */
function pointAt(event: PointerEvent) {
  const el = frame.value
  if (!el || !scale.value) return { x: 0, y: 0 }
  const rect = el.getBoundingClientRect()
  return {
    x: (event.clientX - rect.left) / scale.value,
    y: (event.clientY - rect.top) / scale.value,
  }
}

function setCrop(next: ImageEditorCrop) {
  crop.value = next
  touched.value = true
  /* A crop that shrank below the width asked for takes the field down with it. */
  if (widthOverride.value !== null) {
    widthOverride.value = Math.min(widthOverride.value, Math.round(next.width))
  }
  emit('crop', rounded(next))
}

function begin(handle: CropHandle | 'move', event: PointerEvent) {
  if (props.disabled || !ready.value || event.button !== 0) return

  event.preventDefault()
  gesture.value = { handle, start: { ...crop.value }, from: pointAt(event) }
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)

  /*
   * By hand, because the `preventDefault` above is what would otherwise have done it —
   * and a crop the reader has just taken hold of but which never took focus leaves the
   * arrow keys with whatever was focused before. That is usually the ratio picker, where
   * an arrow quietly changes the ratio instead of nudging the crop.
   */
  cropBox.value?.focus()
}

/** A drag on the picture itself draws a new crop, rather than doing nothing. */
function beginDraw(event: PointerEvent) {
  if (props.disabled || !ready.value || event.button !== 0) return

  const from = pointAt(event)
  crop.value = { x: from.x, y: from.y, width: 0, height: 0 }
  begin('se', event)
}

function onMove(event: PointerEvent) {
  const current = gesture.value
  if (!current) return

  const point = pointAt(event)

  if (current.handle === 'move') {
    setCrop(moveCrop(current.start, point.x - current.from.x, point.y - current.from.y, work.value))
    return
  }

  setCrop(
    resizeCrop(current.handle, point, current.start, work.value, {
      ratio: ratio.value,
      min: props.minSize,
    }),
  )
}

function endGesture(event: PointerEvent) {
  if (!gesture.value) return
  gesture.value = null
  const el = event.currentTarget as HTMLElement
  if (el.hasPointerCapture?.(event.pointerId)) el.releasePointerCapture(event.pointerId)
}

/** Arrows move the crop, shift and the arrows resize it — the drag, for a keyboard. */
function onKeydown(event: KeyboardEvent) {
  if (props.disabled || !ready.value) return

  const step = event.altKey ? 1 : 10
  const dx = event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0
  const dy = event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0
  if (!dx && !dy) return

  event.preventDefault()

  if (!event.shiftKey) {
    setCrop(moveCrop(crop.value, dx, dy, work.value))
    return
  }

  const corner = {
    x: crop.value.x + crop.value.width + dx,
    y: crop.value.y + crop.value.height + dy,
  }
  setCrop(
    resizeCrop('se', corner, crop.value, work.value, { ratio: ratio.value, min: props.minSize }),
  )
}

/* ------------------------------------------------------------------- turning --- */

function turn(direction: 1 | -1) {
  if (props.disabled || !ready.value) return

  const before = work.value
  crop.value = turnCrop(crop.value, before, direction)
  rotation.value = (rotation.value + direction * 90 + 360) % 360
  touched.value = true

  /* The output keeps its shape, so a locked crop has to be laid out afresh. */
  if (ratio.value) crop.value = fitRatio(crop.value, ratio.value, work.value)
  emit('crop', rounded(crop.value))
}

function mirror(axis: 'x' | 'y') {
  if (props.disabled || !ready.value) return

  const flag = axis === 'x' ? flipX : flipY
  flag.value = !flag.value
  setCrop(mirrorCrop(crop.value, work.value, axis))
}

/* -------------------------------------------------------------------- output --- */

/** What the result would be at its own size, once the caps have had their say. */
const fullSize = computed(() =>
  fitInside(crop.value.width, crop.value.height, props.maxWidth, props.maxHeight),
)

/**
 * The size the result will actually be written at: the crop's own pixels, unless the
 * reader has asked for fewer.
 *
 * The width is kept as a fraction rather than a whole number of pixels so that a height
 * typed into the other field comes back as exactly that height — rounded first, it would
 * read back a pixel out and look as though the field had refused what was typed.
 */
const output = computed(() => {
  const full = fullSize.value
  const width = clamp(widthOverride.value ?? full.width, 1, full.width)
  return {
    width: Math.max(1, Math.round(width)),
    height: Math.max(1, Math.round((width * full.height) / (full.width || 1))),
  }
})

function setOutputWidth(value: number | null | undefined) {
  widthOverride.value = value == null ? null : clamp(value, 1, fullSize.value.width)
}

/** The same size, asked for from the other side. */
function setOutputHeight(value: number | null | undefined) {
  if (value == null) {
    widthOverride.value = null
    return
  }
  const full = fullSize.value
  widthOverride.value = clamp((value * full.width) / (full.height || 1), 1, full.width)
}

/**
 * The last part of the URL, which is where a name and an extension would be — taken
 * before the extension is looked for, or a host with a dot in it answers instead.
 */
const basename = computed(() =>
  decodeURIComponent(source.value.split(/[?#]/)[0]?.split('/').pop() ?? ''),
)

/** JPEG cannot be asked to remember transparency, so `auto` keeps a PNG a PNG. */
const type = computed(() => {
  if (props.format !== 'auto') return props.format

  /* What the file says it is, and failing that what it is called. */
  const given = typeof props.src === 'string' ? '' : props.src.type
  const guess = given || `image/${(basename.value.split('.').pop() ?? '').toLowerCase()}`
  if (guess === 'image/png' || guess === 'image/webp') return guess
  return 'image/jpeg'
})

const extensions: Record<string, string> = {
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'image/webp': 'webp',
}

/** The name, with its extension put right: a PNG written as a JPEG is not a `.png`. */
const name = computed(() => {
  const given =
    props.fileName ??
    (typeof props.src !== 'string' && 'name' in props.src ? (props.src as File).name : '')
  const base = (given || basename.value || 'image').replace(/\.[^./\\]+$/, '')
  return `${base || 'image'}.${extensions[type.value] ?? 'jpg'}`
})

function rounded(value: ImageEditorCrop): ImageEditorCrop {
  return {
    x: Math.round(value.x),
    y: Math.round(value.y),
    width: Math.round(value.width),
    height: Math.round(value.height),
  }
}

function encode(canvas: HTMLCanvasElement): Promise<Blob> {
  return new Promise((resolve, reject) => {
    if (typeof canvas.toBlob !== 'function') {
      reject(new Error('This browser cannot write a canvas out'))
      return
    }

    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error('The picture could not be encoded'))),
      type.value,
      props.quality,
    )
  })
}

/**
 * Draws the result and hands it over. Exposed, so a page with a footer of its own can
 * ask for it without the editor carrying one.
 */
async function apply(): Promise<ImageEditorResult | undefined> {
  const el = image.value
  if (!ready.value || !el || busy.value) return undefined

  busy.value = true
  try {
    const canvas = document.createElement('canvas')
    canvas.width = output.value.width
    canvas.height = output.value.height

    const context = canvas.getContext('2d')
    if (!context) throw new Error('This browser gave no 2D context')

    context.imageSmoothingQuality = 'high'

    /* Transparency turns black in a format that has none, which reads as a bug. */
    if (type.value === 'image/jpeg') {
      context.fillStyle = props.background
      context.fillRect(0, 0, canvas.width, canvas.height)
    }

    /*
     * Two transforms, composed: the picture's own pixels onto the turned picture, and the
     * crop out of that onto the canvas. Written as one so the source is sampled once.
     */
    const zoom = output.value.width / (crop.value.width || 1)
    context.setTransform(zoom, 0, 0, zoom, -crop.value.x * zoom, -crop.value.y * zoom)
    context.translate(work.value.width / 2, work.value.height / 2)
    context.scale(flipX.value ? -1 : 1, flipY.value ? -1 : 1)
    context.rotate((rotation.value * Math.PI) / 180)
    context.drawImage(
      el,
      -natural.value.width / 2,
      -natural.value.height / 2,
      natural.value.width,
      natural.value.height,
    )

    const blob = await encode(canvas)
    const result: ImageEditorResult = {
      blob,
      file: new File([blob], name.value, { type: blob.type || type.value }),
      type: blob.type || type.value,
      width: canvas.width,
      height: canvas.height,
      crop: rounded(crop.value),
      rotation: rotation.value,
      flipX: flipX.value,
      flipY: flipY.value,
    }

    emit('save', result)
    return result
  } catch (error) {
    emit('error', error)
    return undefined
  } finally {
    busy.value = false
  }
}

const dirty = computed(() => touched.value || widthOverride.value !== null)

defineExpose({
  /** Draws the result, emits `save` and answers with it. */
  apply,
  /** Back to the whole picture, the right way up. */
  reset: () => reset(),
  crop: computed(() => rounded(crop.value)),
})
</script>

<template>
  <div class="wx-image-editor" :class="{ 'is-disabled': disabled, 'is-busy': busy }">
    <div class="wx-image-editor__stage">
      <!--
        The room the picture is fitted into, held a little inside the stage: the grips
        stand half outside the picture, and with the picture against the edge of a box
        that clips, that half is cut off — and, with it, half of every corner's target.
      -->
      <div ref="stage" class="wx-image-editor__room">
        <div
          v-show="ready"
          ref="frame"
          class="wx-image-editor__frame"
          :style="frameStyle"
          @pointerdown="beginDraw"
          @pointermove="onMove"
          @pointerup="endGesture"
          @pointercancel="endGesture"
        >
          <!--
          What is clipped: the turned picture, which is wider than its frame whenever it
          is on its side, and the shadow that darkens everything outside the crop — one
          shadow big enough to reach every corner, rather than four boxes around the crop
          that would have to be kept in step with it.

          The crop box itself is deliberately not in here. Clipping takes the pointer with
          it, and the grips straddle the edge on purpose: inside a clipped box the outer
          half of every corner grip is dead, which is half the target at the four places
          where aim matters most — and a drag that misses lands on the crop underneath and
          silently does nothing.
        -->
          <div class="wx-image-editor__view">
            <img
              ref="image"
              class="wx-image-editor__picture"
              :src="source"
              :crossorigin="crossOrigin || undefined"
              :style="imageStyle"
              alt=""
              draggable="false"
              @load="onLoad"
              @error="onError"
            />

            <span class="wx-image-editor__shade" :style="cropStyle" aria-hidden="true" />
          </div>

          <div
            ref="cropBox"
            class="wx-image-editor__crop"
            :class="{ 'is-dragging': gesture !== null }"
            :style="cropStyle"
            role="group"
            :aria-label="cropLabel"
            :tabindex="disabled ? -1 : 0"
            @pointerdown.stop="begin('move', $event)"
            @pointermove="onMove"
            @pointerup="endGesture"
            @pointercancel="endGesture"
            @keydown="onKeydown"
          >
            <span class="wx-image-editor__thirds" aria-hidden="true" />
            <span
              v-for="handle in handles"
              :key="handle"
              class="wx-image-editor__handle"
              :class="`is-${handle}`"
              aria-hidden="true"
              @pointerdown.stop="begin(handle, $event)"
              @pointermove="onMove"
              @pointerup="endGesture"
              @pointercancel="endGesture"
            />
          </div>
        </div>
      </div>

      <p v-if="failed" class="wx-image-editor__failed">
        <wx-icon name="warning" size="lg" />
        {{ errorText }}
      </p>
    </div>

    <div class="wx-image-editor__toolbar">
      <wx-segmented
        v-if="options.length > 1"
        :model-value="chosen"
        class="wx-image-editor__ratios"
        size="sm"
        :aria-label="ratioLabel"
        :disabled="disabled || !ready"
        :options="options.map((option) => ({ label: option.label, value: option.value }))"
        @update:model-value="chooseRatio($event)"
      />

      <span v-if="rotatable || flippable" class="wx-image-editor__tools">
        <wx-action
          v-if="rotatable"
          icon="rotate-left"
          size="sm"
          :title="rotateLeftLabel"
          :disabled="disabled || !ready"
          @click="turn(-1)"
        />
        <wx-action
          v-if="rotatable"
          icon="rotate-right"
          size="sm"
          :title="rotateRightLabel"
          :disabled="disabled || !ready"
          @click="turn(1)"
        />
        <wx-action
          v-if="flippable"
          icon="flip-horizontal"
          size="sm"
          :title="flipHorizontalLabel"
          :disabled="disabled || !ready"
          @click="mirror('x')"
        />
        <wx-action
          v-if="flippable"
          icon="flip-vertical"
          size="sm"
          :title="flipVerticalLabel"
          :disabled="disabled || !ready"
          @click="mirror('y')"
        />
      </span>

      <!--
        Named, and both sides of it: two numbers with nothing to say what they measure
        read as the crop, the picture or the panel with equal ease, and a lone width
        leaves the reader to work out what happened to the height.
      -->
      <span class="wx-image-editor__size">
        <wx-tooltip :content="outputHint">
          <span class="wx-image-editor__caption">{{ outputLabel }}</span>
        </wx-tooltip>

        <wx-input-number
          v-if="resizable"
          :model-value="output.width"
          class="wx-image-editor__field"
          size="sm"
          :min="1"
          :max="fullSize.width"
          :controls="false"
          :aria-label="widthLabel"
          :disabled="disabled || !ready"
          @update:model-value="setOutputWidth"
        />
        <span v-else class="wx-image-editor__number">{{ output.width }}</span>

        <span class="wx-image-editor__times" aria-hidden="true">×</span>

        <wx-input-number
          v-if="resizable"
          :model-value="output.height"
          class="wx-image-editor__field"
          size="sm"
          :min="1"
          :max="fullSize.height"
          :controls="false"
          :aria-label="heightLabel"
          :disabled="disabled || !ready"
          @update:model-value="setOutputHeight"
        />
        <span v-else class="wx-image-editor__number">{{ output.height }}</span>

        <span class="wx-image-editor__unit">px</span>
      </span>

      <wx-button
        class="wx-image-editor__reset"
        size="sm"
        variant="text"
        :disabled="disabled || !ready || !dirty"
        @click="reset()"
      >
        {{ resetLabel }}
      </wx-button>
    </div>

    <div v-if="footer" class="wx-image-editor__footer">
      <wx-button variant="outline" :disabled="busy" @click="emit('cancel')">
        {{ cancelLabel }}
      </wx-button>
      <wx-button type="primary" :loading="busy" :disabled="disabled || !ready" @click="apply()">
        {{ saveLabel }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-image-editor {
  /* The box a grip is caught by. Half of it hangs outside the picture, so it sets the
   * margin the picture is held inside the stage by as well. */
  --wx-image-editor-grip: 16px;
  /* The chequer behind a transparent picture: a hint that something is see-through,
   * not a pattern to be read. Loud in a dark theme otherwise. */
  --wx-image-editor-check: color-mix(in srgb, var(--wx-border-muted) 45%, transparent);

  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
  min-width: 0;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

/* A finger is not a mouse pointer: the same drawing, a target twice the size. */
@media (pointer: coarse) {
  .wx-image-editor {
    --wx-image-editor-grip: 30px;
  }
}

.wx-image-editor.is-disabled {
  opacity: 0.6;
}

/*
 * The stage is the room the picture is fitted into, and it is a checkerboard because a
 * transparent PNG over a flat colour is a picture you cannot see the edges of.
 */
.wx-image-editor__stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  min-height: 240px;
  height: var(--wx-image-editor-height, 42vh);
  background-color: var(--wx-bg-body);
  background-image:
    linear-gradient(45deg, var(--wx-image-editor-check) 25%, transparent 25%),
    linear-gradient(-45deg, var(--wx-image-editor-check) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, var(--wx-image-editor-check) 75%),
    linear-gradient(-45deg, transparent 75%, var(--wx-image-editor-check) 75%);
  background-position:
    0 0,
    0 8px,
    8px -8px,
    -8px 0;
  background-size: 16px 16px;
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
  user-select: none;
  touch-action: none;
}

/*
 * Held inside the stage by the half a grip that stands outside the picture. `overflow`
 * clips at the padding box, so what hangs into this margin is drawn and can be clicked;
 * what leaves the stage altogether still goes.
 */
.wx-image-editor__room {
  position: absolute;
  inset: calc(var(--wx-image-editor-grip) / 2);
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Never shrunk: its size is the fitted picture, and a shrunk frame is a stretched one. */
.wx-image-editor__frame {
  position: relative;
  flex: none;
  cursor: crosshair;
}

/* Everything that has to be cut off at the edge of the picture, and nothing else. */
.wx-image-editor__view {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
}

.wx-image-editor__picture {
  position: absolute;
  top: 50%;
  left: 50%;
  transform-origin: center;
  pointer-events: none;
}

/* Big enough to reach the corner of any stage, and cut off at the picture's edge. */
.wx-image-editor__shade {
  position: absolute;
  box-shadow: 0 0 0 9999px rgb(0 0 0 / 45%);
}

.wx-image-editor__crop {
  position: absolute;
  box-sizing: border-box;
  border: 1px solid var(--wx-color-primary);
  cursor: move;
  outline-offset: 2px;
}

.wx-image-editor__crop:focus-visible {
  outline: 2px solid var(--wx-color-primary);
}

/* The thirds, which is what a crop is judged against. */
.wx-image-editor__thirds {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(
      to right,
      transparent 33.33%,
      rgb(255 255 255 / 35%) 33.33% 33.5%,
      transparent 33.5%
    ),
    linear-gradient(
      to right,
      transparent 66.5%,
      rgb(255 255 255 / 35%) 66.5% 66.67%,
      transparent 66.67%
    ),
    linear-gradient(
      to bottom,
      transparent 33.33%,
      rgb(255 255 255 / 35%) 33.33% 33.5%,
      transparent 33.5%
    ),
    linear-gradient(
      to bottom,
      transparent 66.5%,
      rgb(255 255 255 / 35%) 66.5% 66.67%,
      transparent 66.67%
    );
  opacity: 0;
  transition: opacity var(--wx-duration-fast) var(--wx-easing-standard);
  pointer-events: none;
}

.wx-image-editor__crop:hover .wx-image-editor__thirds,
.wx-image-editor__crop.is-dragging .wx-image-editor__thirds {
  opacity: 1;
}

/*
 * The grips are drawn small and hit large: the box around each one is twice the square
 * that is visible, so a corner can be caught without taking aim at four pixels.
 */
.wx-image-editor__handle {
  position: absolute;
  width: var(--wx-image-editor-grip);
  height: var(--wx-image-editor-grip);
  background: transparent;
}

.wx-image-editor__handle::after {
  position: absolute;
  inset: calc(var(--wx-image-editor-grip) / 2 - 3px);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-color-primary);
  border-radius: 1px;
  content: '';
}

.wx-image-editor__handle.is-n,
.wx-image-editor__handle.is-s {
  left: 50%;
  margin-left: calc(var(--wx-image-editor-grip) / -2);
  cursor: ns-resize;
}

.wx-image-editor__handle.is-e,
.wx-image-editor__handle.is-w {
  top: 50%;
  margin-top: calc(var(--wx-image-editor-grip) / -2);
  cursor: ew-resize;
}

.wx-image-editor__handle.is-n,
.wx-image-editor__handle.is-ne,
.wx-image-editor__handle.is-nw {
  top: calc(var(--wx-image-editor-grip) / -2);
}

.wx-image-editor__handle.is-s,
.wx-image-editor__handle.is-se,
.wx-image-editor__handle.is-sw {
  bottom: calc(var(--wx-image-editor-grip) / -2);
}

.wx-image-editor__handle.is-w,
.wx-image-editor__handle.is-nw,
.wx-image-editor__handle.is-sw {
  left: calc(var(--wx-image-editor-grip) / -2);
}

.wx-image-editor__handle.is-e,
.wx-image-editor__handle.is-ne,
.wx-image-editor__handle.is-se {
  right: calc(var(--wx-image-editor-grip) / -2);
}

.wx-image-editor__handle.is-nw,
.wx-image-editor__handle.is-se {
  cursor: nwse-resize;
}

.wx-image-editor__handle.is-ne,
.wx-image-editor__handle.is-sw {
  cursor: nesw-resize;
}

.wx-image-editor__failed {
  display: flex;
  gap: var(--wx-space-8);
  align-items: center;
  margin: 0;
  color: var(--wx-text-muted);
}

.wx-image-editor__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  align-items: center;
}

.wx-image-editor__ratios {
  min-width: 0;
}

.wx-image-editor__tools {
  display: flex;
  gap: 2px;
  align-items: center;
}

/* Pushed to the end, with the reset after it — the two things that are not the picture. */
.wx-image-editor__size {
  display: flex;
  gap: var(--wx-space-4);
  align-items: center;
  margin-inline-start: auto;
  color: var(--wx-text-muted);
  font-variant-numeric: tabular-nums;
}

/* Wide enough for five digits — a camera's four, and a scan's five. */
.wx-image-editor__field {
  width: 74px;
}

.wx-image-editor__caption {
  /* Dotted, the way a word with something behind it is written everywhere else. */
  border-bottom: 1px dotted var(--wx-border-default);
  cursor: help;
}

.wx-image-editor__number {
  color: var(--wx-text-default);
}

.wx-image-editor__times,
.wx-image-editor__unit {
  color: var(--wx-text-placeholder);
}

.wx-image-editor__footer {
  display: flex;
  gap: var(--wx-space-8);
  justify-content: flex-end;
}

@media (prefers-reduced-motion: reduce) {
  .wx-image-editor__thirds {
    transition: none;
  }
}
</style>
