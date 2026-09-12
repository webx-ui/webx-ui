<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'
import WxAction from '../Action/Action.vue'
import WxActions from '../Actions/Actions.vue'
import WxDropdownItem from '../DropdownItem/DropdownItem.vue'
import WxIcon from '../Icon/Icon.vue'
import WxImage from '../Image/Image.vue'
import WxInput from '../Input/Input.vue'
import WxTooltip from '../Tooltip/Tooltip.vue'
import { useElementWidth } from '../../composables/useElementWidth'
import { extensionOf, fileIconName, isPicture } from './files'
import type { FileCardEmits, FileCardProps } from './types'

defineOptions({ name: 'WxFileCard' })

/*
 * One file in a media library: what it looks like, what it is called, and the three or
 * four things that can be done to it.
 *
 * It does none of them. Renaming reports a name, the edit action reports that somebody
 * wants an editor, deleting reports a wish — the file is not touched until whatever
 * holds the cards says so. The one exception is the clipboard, which is not the server:
 * copying a URL is finished the moment it happens.
 */
const props = withDefaults(defineProps<FileCardProps>(), {
  url: undefined,
  thumbnail: undefined,
  type: undefined,
  icon: undefined,
  selected: false,
  size: 'md',
  disabled: false,
  renamable: false,
  editable: false,
  removable: false,
  copyable: false,
  renameLabel: 'Rename',
  editLabel: 'Edit picture',
  removeLabel: 'Delete',
  copyLabel: 'Copy link',
  copiedLabel: 'Copied',
})

const emit = defineEmits<FileCardEmits>()

defineSlots<{
  /** The whole preview box — a video still, a player, whatever the file really needs. */
  preview?: (props: { picture: boolean }) => unknown
  /** Actions of your own, after the ones the card offers. */
  actions?: () => unknown
  /** Under the name: a size, a date, dimensions. */
  meta?: () => unknown
}>()

const picture = computed(() => isPicture(props.name, props.type))
const source = computed(() => props.thumbnail ?? props.url)
const extension = computed(() => extensionOf(props.name))
const glyph = computed(() => props.icon ?? fileIconName(props.name))

/* ------------------------------------------------------------------ renaming --- */

const renaming = ref(false)
const draft = ref('')
const field = useTemplateRef<{ select: () => void }>('field')

async function startRename() {
  if (props.disabled || !props.renamable) return
  draft.value = props.name
  renaming.value = true
  /*
   * Selected rather than merely focused, and the extension is part of the selection:
   * a rename is usually a new name, and the reader who only wanted to fix a typo has
   * lost nothing by having to press End first.
   */
  await nextTick()
  field.value?.select()
}

function commitRename() {
  if (!renaming.value) return
  const next = draft.value.trim()
  renaming.value = false
  if (next && next !== props.name) emit('rename', next)
}

function cancelRename() {
  /* Put the name back before closing: whatever commits next has nothing to report. */
  draft.value = props.name
  renaming.value = false
}

/* ----------------------------------------------------------------- clipboard --- */

const copied = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | undefined

async function copy() {
  const url = props.url
  if (!url) return

  /*
   * Asked for rather than assumed. The clipboard is missing outside a secure context,
   * and `await undefined` would have the card report a copy that never happened.
   */
  if (!navigator.clipboard?.writeText) {
    emit('copy-error', new Error('The clipboard is not available here'))
    return
  }

  try {
    await navigator.clipboard.writeText(url)
    copied.value = true
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => {
      copied.value = false
    }, 1600)
    emit('copy', url)
  } catch (error) {
    emit('copy-error', error)
  }
}

onBeforeUnmount(() => clearTimeout(copiedTimer))

/* ---------------------------------------------------------------------- name --- */

const label = useTemplateRef<HTMLElement>('label')
const labelWidth = useElementWidth(label)

/*
 * A tip repeating a name that is fully visible is noise, so the tip is only armed when
 * the name is actually cut off. The width is watched because the answer changes with
 * the column, not only with the name.
 */
const truncated = ref(false)

watch(
  [labelWidth, () => props.name],
  async () => {
    await nextTick()
    const el = label.value
    truncated.value = Boolean(el) && el!.scrollWidth > el!.clientWidth + 1
  },
  { immediate: true },
)

/* -------------------------------------------------------------------- render --- */

const shows = computed(() => ({
  rename: props.renamable && !props.disabled,
  /* Nothing to open for a `.zip`: the editor this asks for is an image editor. */
  edit: props.editable && picture.value && !props.disabled,
  copy: props.copyable && Boolean(props.url) && !props.disabled,
  remove: props.removable && !props.disabled,
}))

const hasActions = computed(() => Object.values(shows.value).some(Boolean))

const classes = computed(() => [
  'wx-file-card',
  `wx-file-card--${props.size}`,
  {
    'is-selected': props.selected,
    'is-disabled': props.disabled,
    'is-renaming': renaming.value,
  },
])
</script>

<template>
  <div :class="classes">
    <div class="wx-file-card__preview">
      <slot name="preview" :picture="picture">
        <wx-image
          v-if="picture && source"
          class="wx-file-card__picture"
          :src="source"
          :alt="name"
          fit="cover"
        >
          <!--
            A picture that will not load is a file like any other, and is drawn as one —
            the same glyph and the same extension, rather than a broken-picture mark that
            says only that something went wrong.
          -->
          <template #error>
            <span class="wx-file-card__file">
              <wx-icon :name="glyph" class="wx-file-card__glyph" />
              <span v-if="extension" class="wx-file-card__extension">{{ extension }}</span>
            </span>
          </template>
        </wx-image>

        <span v-else class="wx-file-card__file">
          <wx-icon :name="glyph" class="wx-file-card__glyph" />
          <span v-if="extension" class="wx-file-card__extension">{{ extension }}</span>
        </span>
      </slot>

      <!--
        Over the preview, and on a pointer that can hover they stay out of the way until
        it does. There is no hovering on a touch screen, so there they simply stand.
      -->
      <wx-actions
        v-if="hasActions || $slots.actions"
        class="wx-file-card__actions"
        size="sm"
        align="end"
        collapse
        :aria-label="`Actions for ${name}`"
      >
        <wx-action
          v-if="shows.edit"
          type="edit"
          icon="crop"
          :title="editLabel"
          @click="emit('edit')"
        />
        <wx-action v-if="shows.rename" type="edit" :title="renameLabel" @click="startRename" />
        <wx-action
          v-if="shows.copy"
          type="link"
          :icon="copied ? 'check' : 'link'"
          :tone="copied ? 'success' : undefined"
          :title="copied ? copiedLabel : copyLabel"
          @click="copy"
        />
        <wx-action v-if="shows.remove" type="remove" :title="removeLabel" @click="emit('remove')" />
        <slot name="actions" />

        <!--
          Folded up, the same actions need their names: a menu of four unlabelled icons
          is worse than the row it replaced. Left alone, `WxActions` puts the row itself
          in the panel, which is exactly those four icons.
        -->
        <template #collapsed>
          <wx-dropdown-item v-if="shows.edit" icon="crop" @click="emit('edit')">
            {{ editLabel }}
          </wx-dropdown-item>
          <wx-dropdown-item v-if="shows.rename" icon="edit" @click="startRename">
            {{ renameLabel }}
          </wx-dropdown-item>
          <wx-dropdown-item v-if="shows.copy" icon="link" @click="copy">
            {{ copied ? copiedLabel : copyLabel }}
          </wx-dropdown-item>
          <wx-dropdown-item v-if="shows.remove" icon="trash" tone="danger" @click="emit('remove')">
            {{ removeLabel }}
          </wx-dropdown-item>
          <slot name="actions" />
        </template>
      </wx-actions>
    </div>

    <div class="wx-file-card__body">
      <wx-input
        v-if="renaming"
        ref="field"
        v-model="draft"
        class="wx-file-card__field"
        size="sm"
        :aria-label="renameLabel"
        @keydown.enter.prevent="commitRename"
        @keydown.esc.prevent="cancelRename"
        @blur="commitRename"
      />

      <wx-tooltip v-else :content="name" :disabled="!truncated">
        <span ref="label" class="wx-file-card__name" :title="undefined" @dblclick="startRename">{{
          name
        }}</span>
      </wx-tooltip>

      <span v-if="$slots.meta" class="wx-file-card__meta"><slot name="meta" /></span>
    </div>
  </div>
</template>

<style scoped>
.wx-file-card {
  --wx-file-card-glyph: 32px;

  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  box-sizing: border-box;
  min-width: 0;
  padding: var(--wx-space-6);
  border: 1px solid transparent;
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
  transition:
    background var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-file-card--sm {
  --wx-file-card-glyph: 24px;

  font-size: var(--wx-font-size-xs);
}

.wx-file-card--lg {
  --wx-file-card-glyph: 44px;
}

.wx-file-card.is-selected {
  background: var(--wx-color-primary-soft);
  border-color: color-mix(in srgb, var(--wx-color-primary) 40%, transparent);
}

.wx-file-card.is-disabled {
  opacity: 0.55;
}

.wx-file-card__preview {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  aspect-ratio: 4 / 3;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-sm);
}

.wx-file-card__picture {
  width: 100%;
  height: 100%;
}

.wx-file-card__file {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  padding: var(--wx-space-4);
}

.wx-file-card__glyph {
  width: var(--wx-file-card-glyph);
  height: var(--wx-file-card-glyph);
  color: var(--wx-text-placeholder);
}

/*
 * The extension in words. The glyphs go by family — a `.docx` and an `.odt` share one —
 * and this is the line that tells them apart, and that says anything at all about the
 * `.sketch` nobody has drawn an icon for.
 */
.wx-file-card__extension {
  max-width: 100%;
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  letter-spacing: 0.04em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

/*
 * `collapse` on the row above folds four buttons into one menu as soon as they stop
 * fitting the preview, which on a card a hundred pixels wide is at once. The cap is
 * there for the frame before the first measurement, so the row is never seen hanging
 * out of the card it belongs to.
 */
.wx-file-card__actions {
  position: absolute;
  top: var(--wx-space-4);
  right: var(--wx-space-4);
  max-width: calc(100% - var(--wx-space-8));
  padding: 2px;
  background: color-mix(in srgb, var(--wx-bg-surface) 88%, transparent);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-sm);
  backdrop-filter: blur(2px);
}

/*
 * Out of the way until the pointer arrives — but only where there is a pointer that can
 * arrive. A touch screen never hovers, and actions that wait for a hover that cannot
 * happen are actions nobody can reach.
 */
@media (hover: hover) {
  .wx-file-card__actions {
    opacity: 0;
    transition: opacity var(--wx-duration-fast) var(--wx-easing-standard);
  }

  .wx-file-card:hover .wx-file-card__actions,
  .wx-file-card:focus-within .wx-file-card__actions {
    opacity: 1;
  }
}

.wx-file-card__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.wx-file-card__name {
  display: block;
  min-width: 0;
  overflow: hidden;
  color: var(--wx-text-default);
  line-height: var(--wx-font-line-height-normal);
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-file-card__meta {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  text-align: center;
}

@media (prefers-reduced-motion: reduce) {
  .wx-file-card,
  .wx-file-card__actions {
    transition: none;
  }
}
</style>
