<script setup lang="ts">
import { computed, ref } from 'vue'
import WxButton from '../Button/Button.vue'
import WxIcon from '../Icon/Icon.vue'
import WxProgress from '../Progress/Progress.vue'
import type { UploadEmits, UploadFile, UploadProps } from './types'

defineOptions({ name: 'WxUpload' })

/*
 * It does not upload anything.
 *
 * That is deliberate, and it is the same rule the rest of the library follows: a
 * component that owns the request owns the URL, the headers, the CSRF token, the
 * retry policy and the error shape — none of which it can know. So this collects
 * files, checks them, and shows how each one is getting on; the caller does the
 * sending and writes the progress back through `v-model`.
 */
const props = withDefaults(defineProps<UploadProps>(), {
  accept: undefined,
  multiple: true,
  maxSize: undefined,
  max: undefined,
  buttonOnly: false,
  buttonText: 'Choose files',
  hint: 'Drop files here, or click to choose',
  gallery: false,
  disabled: false,
  removable: true,
  beforeAdd: undefined,
})

const emit = defineEmits<UploadEmits>()

defineSlots<{
  /** Replaces the inside of the drop zone. */
  default?: (props: { open: () => void; dragging: boolean }) => unknown
  /** Replaces the row a file is shown as. */
  file?: (props: { file: UploadFile; remove: () => void }) => unknown
  /** Under the zone: a note about formats or sizes. */
  footer?: () => unknown
}>()

const files = defineModel<UploadFile[]>({ default: () => [] })

const input = ref<HTMLInputElement | null>(null)
const dragging = ref(false)

let counter = 0

function open() {
  if (props.disabled) return
  input.value?.click()
}

function idFor(file: File) {
  counter += 1
  return `${Date.now().toString(36)}-${counter}-${file.name}`
}

function matchesAccept(file: File) {
  if (!props.accept) return true

  return props.accept
    .split(',')
    .map((rule) => rule.trim().toLowerCase())
    .filter(Boolean)
    .some((rule) => {
      if (rule.startsWith('.')) return file.name.toLowerCase().endsWith(rule)
      if (rule.endsWith('/*')) return file.type.startsWith(rule.slice(0, -1))
      return file.type.toLowerCase() === rule
    })
}

async function take(incoming: FileList | File[]) {
  if (props.disabled) return

  const added: UploadFile[] = []

  for (const file of Array.from(incoming)) {
    if (props.max !== undefined && files.value.length + added.length >= props.max) {
      emit('reject', file, 'count')
      continue
    }

    if (!matchesAccept(file)) {
      emit('reject', file, 'type')
      continue
    }

    if (props.maxSize !== undefined && file.size > props.maxSize) {
      emit('reject', file, 'size')
      continue
    }

    if (props.beforeAdd && (await props.beforeAdd(file)) === false) {
      emit('reject', file, 'rejected')
      continue
    }

    added.push({
      id: idFor(file),
      name: file.name,
      size: file.size,
      type: file.type,
      status: 'ready',
      progress: 0,
      raw: file,
    })
  }

  if (added.length === 0) return

  files.value = props.multiple ? [...files.value, ...added] : added.slice(-1)
  emit('add', added)
}

function onPick(event: Event) {
  const element = event.target as HTMLInputElement
  if (element.files) void take(element.files)
  /* Cleared, so choosing the same file twice in a row still fires a change. */
  element.value = ''
}

function onDrop(event: DragEvent) {
  dragging.value = false
  if (event.dataTransfer?.files) void take(event.dataTransfer.files)
}

function remove(file: UploadFile) {
  files.value = files.value.filter((known) => known.id !== file.id)
  emit('remove', file)
}

/** Sizes as a reader would say them, which is never in bytes past a thousand. */
function readableSize(bytes: number) {
  if (bytes <= 0) return ''
  const units = ['B', 'kB', 'MB', 'GB']
  const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const value = bytes / 1024 ** power
  return `${value.toFixed(power === 0 ? 0 : 1)} ${units[power]}`
}

const full = computed(() => props.max !== undefined && files.value.length >= props.max)

const classes = computed(() => [
  'wx-upload',
  {
    'wx-upload--gallery': props.gallery,
    'is-dragging': dragging.value,
    'is-disabled': props.disabled,
  },
])
</script>

<template>
  <div :class="classes">
    <input
      ref="input"
      class="wx-upload__input"
      type="file"
      :accept="accept"
      :multiple="multiple"
      :disabled="disabled"
      tabindex="-1"
      @change="onPick"
    />

    <wx-button v-if="buttonOnly" :disabled="disabled || full" @click="open">
      <template #icon><wx-icon name="upload" /></template>
      {{ buttonText }}
    </wx-button>

    <!--
      A button rather than a div with a click handler: this is the control that opens
      the file picker, and a reader who cannot use a pointer has to be able to reach
      it and press it like anything else.
    -->
    <button
      v-else
      type="button"
      class="wx-upload__zone"
      :disabled="disabled || full"
      @click="open"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <slot :open="open" :dragging="dragging">
        <wx-icon class="wx-upload__glyph" name="upload" />
        <span class="wx-upload__hint">{{ hint }}</span>
      </slot>
    </button>

    <ul v-if="files.length > 0" class="wx-upload__files">
      <li v-for="file in files" :key="file.id" class="wx-upload__file" :class="`is-${file.status}`">
        <slot name="file" :file="file" :remove="() => remove(file)">
          <wx-icon
            class="wx-upload__file-icon"
            :name="file.type.startsWith('image/') ? 'image' : 'file'"
          />

          <div class="wx-upload__file-text">
            <button type="button" class="wx-upload__file-name" @click="emit('select', file)">
              {{ file.name }}
            </button>

            <span v-if="file.error" class="wx-upload__file-error">{{ file.error }}</span>
            <span v-else-if="file.size" class="wx-upload__file-size">
              {{ readableSize(file.size) }}
            </span>
          </div>

          <wx-progress
            v-if="file.status === 'uploading'"
            class="wx-upload__file-progress"
            size="sm"
            :value="file.progress"
          />

          <wx-icon v-else-if="file.status === 'done'" class="wx-upload__file-done" name="check" />

          <button
            v-if="removable"
            type="button"
            class="wx-upload__file-remove"
            :aria-label="`Remove ${file.name}`"
            @click="remove(file)"
          >
            <wx-icon name="close" />
          </button>
        </slot>
      </li>
    </ul>

    <div v-if="$slots.footer" class="wx-upload__footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<style scoped>
.wx-upload {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
  box-sizing: border-box;
  font-family: var(--wx-font-family-sans);
}

/* The real input is never seen; the zone above it is what is pressed. */
.wx-upload__input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.wx-upload__zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-6);
  box-sizing: border-box;
  width: 100%;
  padding: var(--wx-space-24);
  background: var(--wx-bg-subtle);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  color: var(--wx-text-muted);
  font: inherit;
  font-size: var(--wx-font-size-sm);
  cursor: pointer;
  transition:
    border-color var(--wx-duration-fast) var(--wx-easing-standard),
    background var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-upload__zone:hover:not(:disabled),
.wx-upload.is-dragging .wx-upload__zone {
  background: var(--wx-color-primary-soft);
  border-color: var(--wx-color-primary);
  color: var(--wx-color-primary);
}

.wx-upload__zone:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-upload__zone:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-upload__glyph {
  width: 24px;
  height: 24px;
}

.wx-upload__files {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-upload--gallery .wx-upload__files {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: var(--wx-space-8);
}

.wx-upload__file {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-6) var(--wx-space-8);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-xs);
  font-size: var(--wx-font-size-sm);
}

.wx-upload__file.is-error {
  border-color: var(--wx-color-danger);
}

.wx-upload__file-icon {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
}

.wx-upload__file-text {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-width: 0;
}

.wx-upload__file-name {
  padding: 0;
  overflow: hidden;
  background: none;
  border: none;
  color: inherit;
  font: inherit;
  text-align: start;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.wx-upload__file-name:hover {
  color: var(--wx-color-primary);
}

.wx-upload__file-size {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-upload__file-error {
  color: var(--wx-color-danger);
  font-size: var(--wx-font-size-xs);
}

.wx-upload__file-progress {
  flex: 0 0 96px;
}

.wx-upload__file-done {
  flex: 0 0 auto;
  color: var(--wx-color-success);
}

.wx-upload__file-remove {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  padding: 0;
  background: none;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-upload__file-remove:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-color-danger);
}

.wx-upload__file-remove:focus-visible,
.wx-upload__file-name:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-upload__footer {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

@media (prefers-reduced-motion: reduce) {
  .wx-upload__zone {
    transition: none;
  }
}
</style>
