<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { TableKit } from '@tiptap/extension-table'
import Image from '@tiptap/extension-image'
import Youtube from '@tiptap/extension-youtube'
import FileHandler from '@tiptap/extension-file-handler'
import { useFormField } from '../../composables/useFormField'
import { useLocalized } from '../../composables/useLocalized'
import LocalePicker from '../Locales/LocalePicker.vue'
import WxRichTextToolbarButton from './ToolbarButton.vue'
import { DEFAULT_ACCEPT, DEFAULT_TOOLS, TABLE_TOOLS, TOOL_META, type TableToolKey } from './tools'
import type {
  RichTextEmits,
  RichTextImage,
  RichTextLabelKey,
  RichTextLabels,
  RichTextModelValue,
  RichTextProps,
  RichTextTool,
} from './types'

defineOptions({ name: 'WxRichText', inheritAttrs: false })

const props = withDefaults(defineProps<RichTextProps>(), {
  placeholder: undefined,
  disabled: undefined,
  readonly: false,
  size: undefined,
  status: undefined,
  id: undefined,
  ariaLabel: undefined,
  minHeight: '220px',
  tools: () => DEFAULT_TOOLS,
  upload: undefined,
  pickImage: undefined,
  accept: () => DEFAULT_ACCEPT,
  labels: undefined,
  localized: false,
})

/**
 * What the editor says when nobody told it otherwise. English is the floor rather than the
 * source of truth: a panel running in ten languages hands its own words in, and a key it
 * forgot stays readable instead of blank.
 */
const DEFAULT_LABELS: RichTextLabels = {
  toolbar: 'Text formatting',
  linkAddress: 'Link address',
  youtubeAddress: 'YouTube URL',
  apply: 'Apply',
  cancel: 'Cancel',
  uploading: 'Uploading…',
}

function label(key: RichTextLabelKey): string {
  const given = props.labels?.[key] ?? DEFAULT_LABELS[key]

  if (given !== undefined) return given
  if (key in TOOL_META) return TOOL_META[key as Exclude<RichTextTool, 'divider'>].label

  return TABLE_TOOLS.find((tool) => tool.key === key)?.label ?? key
}

const emit = defineEmits<RichTextEmits>()

/**
 * The picture node, taught one attribute of its own.
 *
 * ProseMirror keeps only the attributes a node declares — anything else is dropped the first
 * time the document is parsed, silently and in both directions — so the library key has to be
 * a declared attribute rather than something written onto the tag.
 */
const LibraryImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      path: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-wx-path'),
        renderHTML: (attributes: Record<string, unknown>) =>
          attributes.path === null || attributes.path === undefined
            ? {}
            : { 'data-wx-path': String(attributes.path) },
      },
    }
  },
})

const model = defineModel<RichTextModelValue>({ default: '' })

const field = useFormField(props)

const locales = useLocalized(props, model)

/** The language on screen, or nothing at all when the field is plain. */
const editing = computed(() => (locales.on.value ? locales.active.value : undefined))

/*
 * One editor, one language at a time. The other languages are not rendered anywhere — an
 * editor is a document, not a line, and four of them stacked is four documents to scroll past
 * to reach the next field. What the chip does is swap the document in this one.
 */
const currentValue = computed(() => locales.read(editing.value))
const focused = ref(false)
const uploading = ref(0)
const fileInput = ref<HTMLInputElement | null>(null)

/** The link and YouTube buttons open one shared bar rather than a `prompt()`. */
const prompt = ref<{ kind: 'link' | 'youtube'; value: string } | null>(null)

const editable = computed(() => !field.disabled.value && !props.readonly)

/** Tiptap renders `<p></p>` for an empty document; a backend wants an empty string. */
function readHtml(): string {
  const instance = editor.value
  if (!instance || instance.isEmpty) return ''
  return instance.getHTML()
}

const editor = useEditor({
  content: currentValue.value,
  editable: editable.value,
  extensions: [
    StarterKit.configure({
      link: {
        openOnClick: false,
        autolink: true,
        HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' },
      },
    }),
    TableKit.configure({ table: { resizable: true } }),
    LibraryImage.configure({ HTMLAttributes: { class: 'wx-rich-text__image' } }),
    Youtube.configure({ nocookie: true, width: 640, height: 360 }),
    FileHandler.configure({
      allowedMimeTypes: props.accept,
      onDrop: (_editor, files, pos) => void uploadFiles(files, pos),
      onPaste: (_editor, files) => void uploadFiles(files),
    }),
  ],
  onUpdate: () => {
    const html = readHtml()
    // Tiptap raises an update for things that are not edits too. Writing the same words back
    // still changes the model's shape — an empty `[]` from the server becomes `{ en: '' }` —
    // and a form that compares snapshots reads that as a change and autosaves a draft.
    if (html === currentValue.value) return
    locales.write(editing.value, html)
    emit('change', html)
  },
  onFocus: () => {
    focused.value = true
    emit('focus')
  },
  onBlur: () => {
    focused.value = false
    emit('blur')
  },
})

/**
 * Only replaces the document when the value really differs, or the caret jumps. Watching the
 * language being edited rather than the model: with a localized field those are different
 * things, and switching the chip has to bring the other language's document in.
 */
watch(currentValue, (value) => {
  const instance = editor.value
  if (!instance) return
  if (value === readHtml()) return
  instance.commands.setContent(value, { emitUpdate: false })
})

/*
 * Without the update Tiptap sends by default: a form locked for the length of a request and
 * unlocked after it would otherwise hear every editor on it "change" at once.
 */
watch(editable, (value) => editor.value?.setEditable(value, false))

const isEmpty = computed(() => editor.value?.isEmpty ?? true)
const inTable = computed(() => editor.value?.isActive('table') ?? false)

const classes = computed(() => [
  'wx-rich-text',
  `wx-rich-text--${field.size.value}`,
  {
    [`wx-rich-text--${field.status.value}`]: field.status.value !== 'default',
    'is-focused': focused.value,
    'is-disabled': field.disabled.value,
    'is-readonly': props.readonly,
    'is-localized': locales.on.value,
  },
])

/** The caret follows the language into the document it just swapped in. */
function chooseLocale(code: string): void {
  locales.active.value = code
  void nextTick(() => editor.value?.commands.focus())
}

function insertImage(image: RichTextImage, pos?: number) {
  const instance = editor.value
  if (!instance) return
  const at = pos ?? instance.state.selection.anchor
  instance
    .chain()
    .focus()
    .insertContentAt(at, {
      type: 'image',
      attrs: { src: image.url, alt: image.alt, path: image.path ?? null },
    })
    .run()
}

/**
 * Uploads happen one file at a time and insert only on success — a failed upload
 * leaves nothing behind to clean up.
 */
async function uploadFiles(files: File[], pos?: number) {
  if (!props.upload) return
  for (const file of files) {
    uploading.value += 1
    try {
      insertImage(await props.upload(file), pos)
    } catch (error) {
      emit('uploadError', error, file)
    } finally {
      uploading.value -= 1
    }
  }
}

async function onImageButton() {
  if (props.pickImage) {
    const chosen = await props.pickImage()

    if (chosen) insertImage(typeof chosen === 'string' ? { url: chosen } : chosen)

    return
  }
  fileInput.value?.click()
}

function onFilePicked(event: Event) {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  void uploadFiles(files)
}

function openPrompt(kind: 'link' | 'youtube') {
  const current = kind === 'link' ? (editor.value?.getAttributes('link').href ?? '') : ''
  prompt.value = { kind, value: String(current) }
}

function applyPrompt() {
  const instance = editor.value
  const current = prompt.value
  if (!instance || !current) return
  const value = current.value.trim()

  if (current.kind === 'link') {
    if (value) instance.chain().focus().extendMarkRange('link').setLink({ href: value }).run()
    else instance.chain().focus().extendMarkRange('link').unsetLink().run()
  } else if (value) {
    instance.commands.setYoutubeVideo({ src: value })
  }

  prompt.value = null
}

/** Whether a tool should render at all — the image button needs somewhere to get a file. */
function toolVisible(tool: RichTextTool): boolean {
  if (tool === 'image') return Boolean(props.upload || props.pickImage)
  return true
}

const visibleTools = computed(() => props.tools.filter(toolVisible))

function isActive(tool: RichTextTool): boolean {
  const instance = editor.value
  if (!instance) return false
  switch (tool) {
    case 'h2':
      return instance.isActive('heading', { level: 2 })
    case 'h3':
      return instance.isActive('heading', { level: 3 })
    case 'h4':
      return instance.isActive('heading', { level: 4 })
    default:
      return instance.isActive(tool)
  }
}

function run(tool: RichTextTool) {
  const instance = editor.value
  if (!instance) return
  const chain = instance.chain().focus()

  switch (tool) {
    case 'bold':
      chain.toggleBold().run()
      break
    case 'italic':
      chain.toggleItalic().run()
      break
    case 'strike':
      chain.toggleStrike().run()
      break
    case 'code':
      chain.toggleCode().run()
      break
    case 'h2':
      chain.toggleHeading({ level: 2 }).run()
      break
    case 'h3':
      chain.toggleHeading({ level: 3 }).run()
      break
    case 'h4':
      chain.toggleHeading({ level: 4 }).run()
      break
    case 'bulletList':
      chain.toggleBulletList().run()
      break
    case 'orderedList':
      chain.toggleOrderedList().run()
      break
    case 'blockquote':
      chain.toggleBlockquote().run()
      break
    case 'hr':
      chain.setHorizontalRule().run()
      break
    case 'undo':
      chain.undo().run()
      break
    case 'redo':
      chain.redo().run()
      break
    case 'table':
      chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
      break
    case 'link':
      openPrompt('link')
      break
    case 'youtube':
      openPrompt('youtube')
      break
    case 'image':
      void onImageButton()
      break
  }
}

function runTableCommand(key: TableToolKey) {
  const instance = editor.value
  if (!instance) return
  const chain = instance.chain().focus() as unknown as Record<string, () => { run: () => void }>
  chain[key]().run()
}

defineExpose({
  editor,
  focus: () => editor.value?.commands.focus(),
  clear: () => editor.value?.commands.clearContent(true),
})
</script>

<template>
  <div :class="classes">
    <div class="wx-rich-text__toolbar" role="toolbar" :aria-label="ariaLabel ?? label('toolbar')">
      <template v-for="(tool, index) in visibleTools" :key="`${tool}-${index}`">
        <span v-if="tool === 'divider'" class="wx-rich-text__divider" aria-hidden="true" />
        <wx-rich-text-toolbar-button
          v-else
          :icon="TOOL_META[tool].icon"
          :text="TOOL_META[tool].text"
          :label="label(tool)"
          :active="isActive(tool)"
          :disabled="!editable"
          @click="run(tool)"
        />
      </template>

      <span v-if="uploading > 0" class="wx-rich-text__uploading">{{ label('uploading') }}</span>
    </div>

    <locale-picker
      v-if="locales.on.value"
      :locales="locales.list.value"
      :active="locales.active.value"
      @choose="chooseLocale"
    />

    <div v-if="inTable && editable" class="wx-rich-text__toolbar wx-rich-text__toolbar--table">
      <wx-rich-text-toolbar-button
        v-for="tool in TABLE_TOOLS"
        :key="tool.key"
        :icon="tool.icon"
        :label="label(tool.key)"
        @click="runTableCommand(tool.key)"
      />
    </div>

    <form v-if="prompt" class="wx-rich-text__prompt" @submit.prevent="applyPrompt">
      <label class="wx-rich-text__prompt-label" :for="`${field.id.value}-prompt`">
        {{ label(prompt.kind === 'link' ? 'linkAddress' : 'youtubeAddress') }}
      </label>
      <input
        :id="`${field.id.value}-prompt`"
        v-model="prompt.value"
        class="wx-rich-text__prompt-input"
        type="url"
        :placeholder="prompt.kind === 'link' ? 'https://example.com' : 'https://youtu.be/…'"
        @keydown.esc="prompt = null"
      />
      <wx-rich-text-toolbar-button icon="check" :label="label('apply')" @click="applyPrompt" />
      <wx-rich-text-toolbar-button icon="close" :label="label('cancel')" @click="prompt = null" />
    </form>

    <div class="wx-rich-text__body" :style="{ minHeight }">
      <span v-if="placeholder && isEmpty" class="wx-rich-text__placeholder">{{ placeholder }}</span>
      <editor-content
        :editor="editor"
        class="wx-rich-text__content"
        :aria-describedby="field.describedBy.value"
      />
    </div>

    <input
      v-if="upload && !pickImage"
      ref="fileInput"
      class="wx-rich-text__file"
      type="file"
      :accept="accept.join(',')"
      multiple
      @change="onFilePicked"
    />
  </div>
</template>

<style scoped>
.wx-rich-text {
  position: relative;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  width: 100%;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  transition: border-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-rich-text.is-focused {
  border-color: var(--wx-border-focus);
}

.wx-rich-text--error,
.wx-rich-text--error.is-focused {
  border-color: var(--wx-color-danger);
}

.wx-rich-text--success {
  border-color: var(--wx-color-success);
}

.wx-rich-text--warning {
  border-color: var(--wx-color-warning);
}

/*
 * The chip sits in the top corner, over the end of the toolbar rather than over the text: a
 * document is written from the top left and that corner has to stay clear. The toolbar keeps
 * its distance so the last button is not underneath it.
 */
.wx-rich-text.is-localized {
  --wx-locale-picker-height: 20px;
  --wx-locale-picker-top: var(--wx-space-4);
  --wx-locale-picker-shift: 0;
}

.wx-rich-text.is-localized .wx-rich-text__toolbar:first-child {
  padding-right: var(--wx-space-40);
}

.wx-rich-text.is-disabled {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
}

.wx-rich-text__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-2);
  padding: var(--wx-space-6);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-rich-text__toolbar--table {
  background: var(--wx-bg-subtle);
}

.wx-rich-text__divider {
  width: 1px;
  height: 18px;
  margin: 0 var(--wx-space-4);
  background: var(--wx-border-default);
}

.wx-rich-text__uploading {
  margin-left: auto;
  padding: 0 var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-rich-text__prompt {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8);
  background: var(--wx-bg-subtle);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-rich-text__prompt-label {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-rich-text__prompt-input {
  flex: 1 1 auto;
  min-width: 0;
  height: var(--wx-size-control-sm);
  padding: 0 var(--wx-space-10);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-xs);
  outline: none;
  color: inherit;
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
}

.wx-rich-text__prompt-input:focus {
  border-color: var(--wx-border-focus);
}

.wx-rich-text__body {
  position: relative;
  flex: 1 1 auto;
  padding: var(--wx-space-12) var(--wx-space-16);
}

.wx-rich-text__placeholder {
  position: absolute;
  top: var(--wx-space-12);
  left: var(--wx-space-16);
  color: var(--wx-text-placeholder);
  pointer-events: none;
}

.wx-rich-text__file {
  display: none;
}

/* The editing surface itself is rendered by ProseMirror. */
.wx-rich-text__content :deep(.ProseMirror) {
  outline: none;
  min-height: inherit;
  line-height: var(--wx-font-line-height-relaxed);
}

/*
 * Host applications decorate content tags globally — VitePress, which renders these
 * docs, gives every h2 a top border and 24px of padding, and turns tables into
 * `display: block`. The editing surface has to look the same wherever it is
 * embedded, so anything a host is likely to set is stated here instead of inherited.
 */
.wx-rich-text__content :deep(h1),
.wx-rich-text__content :deep(h2),
.wx-rich-text__content :deep(h3),
.wx-rich-text__content :deep(h4),
.wx-rich-text__content :deep(h5),
.wx-rich-text__content :deep(h6) {
  margin: var(--wx-space-18) 0 var(--wx-space-8);
  padding: 0;
  border: 0;
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-rich-text__content :deep(p) {
  margin: 0 0 var(--wx-space-12);
}

/* Nothing pushes the document away from the top or bottom edge of the field. */
.wx-rich-text__content :deep(.ProseMirror > :first-child) {
  margin-top: 0;
}

.wx-rich-text__content :deep(.ProseMirror > :last-child) {
  margin-bottom: 0;
}

.wx-rich-text__content :deep(h2) {
  font-size: var(--wx-font-size-2xl);
}

.wx-rich-text__content :deep(h3) {
  font-size: var(--wx-font-size-xl);
}

.wx-rich-text__content :deep(h4) {
  font-size: var(--wx-font-size-lg);
}

.wx-rich-text__content :deep(ul),
.wx-rich-text__content :deep(ol) {
  margin: 0 0 var(--wx-space-12);
  padding-left: var(--wx-space-24);
}

.wx-rich-text__content :deep(blockquote) {
  margin: 0 0 var(--wx-space-12);
  padding-left: var(--wx-space-16);
  border-left: 3px solid var(--wx-color-primary-soft);
  color: var(--wx-text-muted);
}

.wx-rich-text__content :deep(a) {
  color: var(--wx-text-link);
  text-decoration: underline;
}

.wx-rich-text__content :deep(code) {
  padding: 2px var(--wx-space-4);
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-xs);
  font-family: var(--wx-font-family-mono);
  font-size: 0.9em;
}

.wx-rich-text__content :deep(pre) {
  margin: 0 0 var(--wx-space-12);
  padding: var(--wx-space-12);
  background: var(--wx-bg-inverse);
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-inverse);
  overflow-x: auto;
}

.wx-rich-text__content :deep(hr) {
  margin: var(--wx-space-18) 0;
  border: none;
  border-top: 1px solid var(--wx-border-default);
}

.wx-rich-text__content :deep(img),
.wx-rich-text__content :deep(iframe) {
  max-width: 100%;
  border-radius: var(--wx-radius-xs);
}

.wx-rich-text__content :deep(img.ProseMirror-selectednode) {
  outline: 2px solid var(--wx-color-primary);
}

.wx-rich-text__content :deep(table) {
  /* Stated because hosts turn tables into `display: block` for horizontal scrolling. */
  display: table;
  width: 100%;
  margin: 0 0 var(--wx-space-12);
  border-collapse: collapse;
  table-layout: fixed;
  overflow: hidden;
}

.wx-rich-text__content :deep(th),
.wx-rich-text__content :deep(td) {
  position: relative;
  padding: var(--wx-space-6) var(--wx-space-10);
  border: 1px solid var(--wx-border-default);
  vertical-align: top;
}

.wx-rich-text__content :deep(th) {
  background: var(--wx-bg-fill);
  font-weight: var(--wx-font-weight-semibold);
  text-align: left;
}

.wx-rich-text__content :deep(.selectedCell::after) {
  content: '';
  position: absolute;
  inset: 0;
  background: var(--wx-color-primary-soft);
  pointer-events: none;
}

.wx-rich-text__content :deep(.column-resize-handle) {
  position: absolute;
  top: 0;
  right: -2px;
  bottom: 0;
  width: 4px;
  background: var(--wx-color-primary);
  cursor: col-resize;
}
</style>
