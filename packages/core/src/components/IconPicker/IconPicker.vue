<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { PopoverAnchor, PopoverContent, PopoverPortal, PopoverRoot } from 'reka-ui'
import { useControlAttrs } from '../../composables/useControlAttrs'
import { useFormField } from '../../composables/useFormField'
import { iconNames } from '../Icon/icons'
import WxIcon from '../Icon/Icon.vue'
import type { IconPickerEmits, IconPickerProps } from './types'

defineOptions({ name: 'WxIconPicker', inheritAttrs: false })

/**
 * The icon set, picked from rather than typed into.
 *
 * An icon is a name in a set of a hundred and fifty, and a name that is not in it draws
 * nothing at all — no warning, no placeholder, just a label that has quietly moved to where
 * the picture should have been. A text field cannot say that; a grid of every icon there is
 * can only say it.
 *
 * So the box is the search and the panel is the answer: typing filters, clicking chooses, and
 * the value only ever becomes a name the set has.
 */
const props = withDefaults(defineProps<IconPickerProps>(), {
  clearable: false,
  placeholder: 'Search icons',
  emptyText: 'No icon of that name',
  clearLabel: 'Clear',
  teleport: true,
  disabled: undefined,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<IconPickerEmits>()

const model = defineModel<string | null>({ default: null })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const field = useFormField(props)
const open = ref(false)
const input = ref<HTMLInputElement | null>(null)

/** What the box shows: the chosen name, or what is being searched for. */
const text = ref(model.value ?? '')

watch(model, (value) => {
  text.value = value ?? ''
})

const all = iconNames()

const shown = computed(() => {
  const query = text.value.trim().toLowerCase()

  /* The whole set until somebody types: a picker that opens empty is a search box with a
     panel, and the point of it is that the icons are there to be looked at. */
  return query === '' || query === model.value ? all : all.filter((name) => name.includes(query))
})

const classes = computed(() => [
  'wx-icon-picker',
  `wx-icon-picker--${field.size.value}`,
  {
    [`wx-icon-picker--${field.status.value}`]: field.status.value !== 'default',
    'is-open': open.value,
    'is-disabled': field.disabled.value,
  },
])

function choose(name: string): void {
  model.value = name
  text.value = name
  emit('change', name)
  open.value = false
}

function clear(): void {
  model.value = null
  text.value = ''
  emit('change', null)
  void nextTick(() => input.value?.focus())
}

function onInput(event: Event): void {
  text.value = (event.target as HTMLInputElement).value
  open.value = true
}

/**
 * Opening on the next turn of the loop, not inside the handler.
 *
 * Focus arrives on mousedown, so a panel opened there is a dismissable layer created in the
 * middle of a click that ends outside it — and Reka closes it again on the mouseup. The field
 * would take focus and nothing would appear. A synthetic `focus()` in a test opens it either
 * way, which is why this can only be seen with a real mouse.
 */
function onFocus(): void {
  setTimeout(() => {
    open.value = true
  })
}

/** One match left and Enter pressed: the search has already chosen. */
function onEnter(): void {
  if (shown.value.length === 1) choose(shown.value[0]!)
}

const grid = ref<HTMLElement | null>(null)

/*
 * A search nobody finished is not a value: what the box holds goes back to what the model
 * holds the moment the panel closes. And on the way in, the panel opens where the chosen icon
 * is — a hundred and fifty tiles with the current one somewhere below the fold is a list that
 * has not answered the first question anybody has of it.
 */
watch(open, (value) => {
  if (!value) {
    text.value = model.value ?? ''

    return
  }

  void nextTick(() => {
    const box = grid.value
    const current = box?.querySelector<HTMLElement>('.is-current')

    /* Its own scroller and not `scrollIntoView`, which would also scroll the page under it. */
    if (box && current) box.scrollTop = current.offsetTop - box.clientHeight / 2
  })
})
</script>

<template>
  <popover-root v-model:open="open">
    <div :class="classes" v-bind="rootAttrs">
      <popover-anchor as-child>
        <div class="wx-icon-picker__field">
          <span class="wx-icon-picker__preview" :class="{ 'is-empty': !model }">
            <wx-icon v-if="model" :name="model" />
          </span>

          <input
            :id="field.id.value"
            ref="input"
            v-bind="controlAttrs"
            class="wx-icon-picker__input"
            type="text"
            autocomplete="off"
            spellcheck="false"
            role="combobox"
            :aria-expanded="open"
            :value="text"
            :name="name"
            :placeholder="placeholder"
            :disabled="field.disabled.value"
            :aria-label="ariaLabel"
            :aria-describedby="field.describedBy.value"
            :aria-invalid="field.status.value === 'error' || undefined"
            @input="onInput"
            @focus="onFocus"
            @keydown.enter.prevent="onEnter"
          />

          <button
            v-if="clearable && model && !field.disabled.value"
            class="wx-icon-picker__clear"
            type="button"
            tabindex="-1"
            :aria-label="clearLabel"
            @click.stop="clear"
          >
            <wx-icon name="close" />
          </button>
        </div>
      </popover-anchor>

      <popover-portal :disabled="!teleport">
        <popover-content
          class="wx-icon-picker__panel"
          :side-offset="4"
          align="start"
          @open-auto-focus.prevent
        >
          <div v-if="shown.length" ref="grid" class="wx-icon-picker__grid">
            <button
              v-for="name in shown"
              :key="name"
              type="button"
              class="wx-icon-picker__option"
              :class="{ 'is-current': name === model }"
              :title="name"
              @click="choose(name)"
            >
              <wx-icon :name="name" size="lg" />
              <span class="wx-icon-picker__name">{{ name }}</span>
            </button>
          </div>
          <p v-else class="wx-icon-picker__empty">{{ emptyText }}</p>
        </popover-content>
      </popover-portal>
    </div>
  </popover-root>
</template>

<style scoped>
.wx-icon-picker {
  display: block;
  width: 100%;
}

.wx-icon-picker__field {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding-inline: var(--wx-space-10);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  background: var(--wx-bg-surface);
  block-size: var(--wx-size-control-md);
}

.wx-icon-picker.is-open .wx-icon-picker__field,
.wx-icon-picker__field:focus-within {
  border-color: var(--wx-color-primary);
  box-shadow: var(--wx-ring-focus);
}

.wx-icon-picker--error .wx-icon-picker__field {
  border-color: var(--wx-color-danger);
}

.wx-icon-picker.is-disabled .wx-icon-picker__field {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

/* Where the chosen icon sits — and, when there is none, the hole it leaves. */
.wx-icon-picker__preview {
  display: grid;
  flex: none;
  place-items: center;
  inline-size: 24px;
  block-size: 24px;
  border-radius: var(--wx-radius-xs);
  font-size: var(--wx-font-size-lg);
  color: var(--wx-text-default);
}

.wx-icon-picker__preview.is-empty {
  border: 1px dashed var(--wx-border-default);
}

.wx-icon-picker__input {
  flex: 1 1 auto;
  min-inline-size: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  outline: none;
}

.wx-icon-picker__clear {
  display: grid;
  place-items: center;
  flex: none;
  padding: 0;
  border: 0;
  background: none;
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-icon-picker__clear:hover {
  color: var(--wx-text-default);
}

.wx-icon-picker--sm .wx-icon-picker__field {
  block-size: var(--wx-size-control-sm);
  padding-inline: var(--wx-space-8);
}

.wx-icon-picker--lg .wx-icon-picker__field {
  block-size: var(--wx-size-control-lg);
  padding-inline: var(--wx-space-14);
}
</style>

<style>
/* Teleported panel: it is portalled out of the component, so the scope attribute never
   reaches it and a scoped rule matches nothing at all — the panel would draw transparent. */
.wx-icon-picker__panel {
  inline-size: min(360px, calc(100vw - var(--wx-space-16)));
  padding: var(--wx-space-8);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-popover);
  z-index: var(--wx-z-index-popover);
}

/* Four across and a scroll: a hundred and fifty icons are a page, not a dropdown. */
.wx-icon-picker__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--wx-space-4);
  max-block-size: 280px;
  overflow-y: auto;
}

.wx-icon-picker__option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-4);
  padding: var(--wx-space-8) var(--wx-space-4);
  border: 1px solid transparent;
  border-radius: var(--wx-radius-sm);
  background: none;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.wx-icon-picker__option:hover {
  background: var(--wx-bg-subtle);
}

.wx-icon-picker__option.is-current {
  border-color: var(--wx-color-primary);
  color: var(--wx-color-primary);
}

/* The name is what gets typed into a template, so it is shown whole or not at all. */
.wx-icon-picker__name {
  max-inline-size: 100%;
  overflow: hidden;
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-icon-picker__empty {
  margin: 0;
  padding: var(--wx-space-12);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: center;
}
</style>
