<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'
import WxAction from '../Action/Action.vue'
import WxActions from '../Actions/Actions.vue'
import WxButton from '../Button/Button.vue'
import WxDropdownItem from '../DropdownItem/DropdownItem.vue'
import WxIcon from '../Icon/Icon.vue'
import WxImage from '../Image/Image.vue'
import WxInput from '../Input/Input.vue'
import WxPopconfirm from '../Popconfirm/Popconfirm.vue'
import WxPopover from '../Popover/Popover.vue'
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
  confirmRemove: true,
  copyable: false,
  renameLabel: 'Rename',
  saveLabel: 'Save',
  cancelLabel: 'Cancel',
  editLabel: 'Edit picture',
  removeLabel: 'Delete',
  removeConfirmText: undefined,
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
const field = useTemplateRef<{ select: () => void; input: HTMLInputElement | null }>('field')

function startRename() {
  if (props.disabled || !props.renamable) return
  draft.value = props.name
  renaming.value = true
}

/* Cleared on the way out, so a card unmounted mid-rename leaves nothing running. */
const selectTimers: ReturnType<typeof setTimeout>[] = []

function stopSelecting() {
  for (const timer of selectTimers.splice(0)) clearTimeout(timer)
}

/**
 * The name, focused and selected whole — extension and all, because a rename is usually a
 * new name, and the reader who only wanted to fix a typo has lost nothing by pressing End
 * first.
 *
 * Hung off the field appearing rather than off the panel opening, because the two are not
 * the same moment: opened from the folded-up menu, the panel waits for that menu to
 * finish closing and the field arrives several ticks after the open.
 */
watch(
  () => field.value?.input,
  (el) => {
    if (!el || !renaming.value) return

    const take = () => {
      if (!renaming.value || !el.isConnected) return
      if (document.activeElement !== el) el.focus()
      el.select()
    }

    take()

    /*
     * And again twice. That same menu hands focus back to its own trigger on the way out,
     * which takes it straight out of the field the panel has just put it in. Opened from
     * the row or from a double click there is nothing to fight, and the first call is the
     * only one that does anything.
     */
    selectTimers.push(setTimeout(take, 0), setTimeout(take, 120))
  },
)

function commitRename() {
  if (!renaming.value) return
  const next = draft.value.trim()
  renaming.value = false
  if (next && next !== props.name) emit('rename', next)
}

/*
 * Every way out of the panel that is not Save: Escape, a click on the page behind it,
 * Cancel. A panel dismissed is a panel dismissed — it does not quietly save on the way
 * out, the way an input in place had to.
 */
function cancelRename() {
  stopSelecting()
  draft.value = props.name
  renaming.value = false
}

/* ------------------------------------------------------------------ deleting --- */

const confirming = ref(false)

/** The question, with the file in it, since a grid of thumbnails looks much alike. */
const removeQuestion = computed(() => props.removeConfirmText ?? `Delete ${props.name}?`)

function askRemove() {
  if (props.confirmRemove) confirming.value = true
  else emit('remove')
}

function confirmRemoval() {
  confirming.value = false
  emit('remove')
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

onBeforeUnmount(() => {
  clearTimeout(copiedTimer)
  stopSelecting()
})

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

/*
 * The folded-up menu's panel is teleported, so the pointer that opened it is no longer
 * over the card — and a row that waits for a hover would fade out from under the menu
 * hanging off it. `WxActions` reports the state for exactly this.
 */
const menuOpen = ref(false)

const classes = computed(() => [
  'wx-file-card',
  `wx-file-card--${props.size}`,
  {
    'is-selected': props.selected,
    'is-disabled': props.disabled,
    /* Every reason the buttons have to stay put while nothing is hovering them. */
    'is-busy': renaming.value || confirming.value || menuOpen.value,
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
      <span v-if="hasActions || $slots.actions" class="wx-file-card__tools">
        <!--
          What the rename panel hangs from: an empty box over the buttons, rather than
          the rename button itself, because that button is not always there — folded up,
          the row is a single "more" and the panel still has to drop from what was
          pressed. It is empty and not a wrapper because a trigger that contained the
          buttons opened the panel whenever one of them was clicked, `disabled` or not:
          the click reaches the trigger on its way up.
        -->
        <wx-popover
          v-model:open="renaming"
          side="bottom"
          align="end"
          :width="240"
          :arrow="false"
          disabled
          :aria-label="renameLabel"
          @close="cancelRename"
        >
          <template #trigger>
            <span class="wx-file-card__anchor" aria-hidden="true" />
          </template>

          <wx-input
            ref="field"
            v-model="draft"
            class="wx-file-card__field"
            size="sm"
            :aria-label="renameLabel"
            @keydown.enter.prevent="commitRename"
          />

          <template #footer>
            <wx-button size="sm" variant="text" @click="renaming = false">{{
              cancelLabel
            }}</wx-button>
            <wx-button size="sm" type="primary" @click="commitRename">{{ saveLabel }}</wx-button>
          </template>
        </wx-popover>

        <!--
          The question before a deletion, on an anchor of its own for the same reason the
          rename panel has one: it is asked from the row and from the folded-up menu, and
          the menu item is gone by the time the answer is wanted.
        -->
        <wx-popconfirm
          v-if="shows.remove && confirmRemove"
          v-model:open="confirming"
          side="bottom"
          align="end"
          :title="removeQuestion"
          :confirm-text="removeLabel"
          :cancel-text="cancelLabel"
          confirm-type="danger"
          :arrow="false"
          disabled
          @confirm="confirmRemoval"
        >
          <template #trigger>
            <span class="wx-file-card__anchor" aria-hidden="true" />
          </template>
        </wx-popconfirm>

        <wx-actions
          v-model:menu-open="menuOpen"
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
          <wx-action v-if="shows.remove" type="remove" :title="removeLabel" @click="askRemove" />
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
            <wx-dropdown-item v-if="shows.remove" icon="trash" tone="danger" @click="askRemove">
              {{ removeLabel }}
            </wx-dropdown-item>
            <slot name="actions" />
          </template>
        </wx-actions>
      </span>
    </div>

    <div class="wx-file-card__body">
      <wx-tooltip :content="name" :disabled="!truncated || renaming">
        <span ref="label" class="wx-file-card__name" @dblclick="startRename">{{ name }}</span>
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
 * The wrapper carries the place and the width; the row inside carries the look. They are
 * two elements because `WxActions` measures itself against its parent, so the parent has
 * to be the thing with a width — and because the rename panel hangs from the wrapper,
 * which stays put whether the row is four buttons or the one it folds into.
 *
 * `collapse` folds those four as soon as they stop fitting, which on a card a hundred
 * pixels wide is at once. The cap is for the frame before the first measurement, so the
 * row is never seen hanging out of the card it belongs to.
 */
/*
 * Both edges, so the width is the preview's and not the row's. Left to shrink around its
 * contents it would be the width of whatever it currently holds — and once the row had
 * folded into a single button, that is the width the row would be measured against next
 * time, so it could never come back out.
 */
.wx-file-card__tools {
  position: absolute;
  top: var(--wx-space-4);
  right: var(--wx-space-4);
  left: var(--wx-space-4);
  display: block;
  /* The row inside sits at the end of it; the span itself is only a measuring stick. */
  pointer-events: none;
}

.wx-file-card__tools > * {
  pointer-events: auto;
}

/* The box the panel is placed against: exactly the buttons, and nothing to click. */
.wx-file-card__anchor {
  position: absolute;
  inset: 0;
  display: block;
  pointer-events: none;
}

/*
 * Shrunk to its buttons and pushed to the end of the measuring stick above, so the
 * background is a pill around them rather than a bar across the picture.
 */
.wx-file-card__actions {
  width: fit-content;
  margin-inline-start: auto;
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
  .wx-file-card__tools {
    opacity: 0;
    transition: opacity var(--wx-duration-fast) var(--wx-easing-standard);
  }

  /*
   * `is-busy` among them because every panel the buttons open is teleported: the menu,
   * the rename field, the question before a deletion. The pointer and the focus are both
   * outside the card while one of them is up, so neither `:hover` nor `:focus-within`
   * holds — and the buttons would fade out from under the panel hanging off them.
   */
  .wx-file-card:hover .wx-file-card__tools,
  .wx-file-card:focus-within .wx-file-card__tools,
  .wx-file-card.is-busy .wx-file-card__tools {
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
  .wx-file-card__tools {
    transition: none;
  }
}
</style>
