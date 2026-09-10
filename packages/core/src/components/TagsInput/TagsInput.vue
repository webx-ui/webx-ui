<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useFormField } from '../../composables/useFormField'
import type { TagsInputEmits, TagsInputProps } from './types'

defineOptions({ name: 'WxTagsInput', inheritAttrs: false })

const props = withDefaults(defineProps<TagsInputProps>(), {
  suggestions: () => [],
  placeholder: undefined,
  emptyText: 'Nothing found',
  max: undefined,
  duplicates: false,
  allowCreate: true,
  disabled: undefined,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<TagsInputEmits>()

const model = defineModel<string[]>({ default: () => [] })

const field = useFormField(props)
const inputRef = ref<HTMLInputElement | null>(null)
const text = ref('')
const open = ref(false)
const highlighted = ref(-1)
/** The tag Backspace has marked; the next Backspace removes it. */
const armed = ref(-1)

const tags = computed(() => model.value ?? [])

const suggestions = computed(() =>
  props.suggestions.filter((suggestion) => !tags.value.includes(suggestion)),
)

const showList = computed(() => open.value && suggestions.value.length > 0)

const full = computed(() => props.max !== undefined && tags.value.length >= props.max)

const classes = computed(() => [
  'wx-tags-input',
  `wx-tags-input--${field.size.value}`,
  {
    [`wx-tags-input--${field.status.value}`]: field.status.value !== 'default',
    'is-open': showList.value,
    'is-disabled': field.disabled.value,
  },
])

watch(suggestions, () => {
  highlighted.value = -1
})

function commit(next: string[]) {
  model.value = next
  emit('change', next)
}

function add(tag: string) {
  const value = tag.trim()
  if (!value || full.value) return
  if (!props.duplicates && tags.value.includes(value)) {
    text.value = ''
    return
  }
  commit([...tags.value, value])
  text.value = ''
  open.value = false
  armed.value = -1
}

function remove(index: number) {
  commit(tags.value.filter((_, position) => position !== index))
  armed.value = -1
}

function onInput(event: Event) {
  const value = (event.target as HTMLInputElement).value
  text.value = value
  armed.value = -1
  highlighted.value = -1
  if (value) open.value = true
  emit('search', value)
}

/**
 * Backspace on an empty field marks the last tag first and removes it on the second
 * press — deleting something the user cannot see would be worse than one extra key.
 */
function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    open.value = false
    armed.value = -1
    return
  }

  if (event.key === 'Backspace' && text.value === '' && tags.value.length > 0) {
    event.preventDefault()
    if (armed.value === tags.value.length - 1) remove(armed.value)
    else armed.value = tags.value.length - 1
    return
  }

  if (event.key === 'ArrowDown' && showList.value) {
    event.preventDefault()
    highlighted.value = (highlighted.value + 1) % suggestions.value.length
    return
  }

  if (event.key === 'ArrowUp' && showList.value) {
    event.preventDefault()
    highlighted.value =
      highlighted.value <= 0 ? suggestions.value.length - 1 : highlighted.value - 1
    return
  }

  if (event.key === 'Enter') {
    event.preventDefault()
    if (showList.value && highlighted.value >= 0) add(suggestions.value[highlighted.value])
    else if (props.allowCreate) add(text.value)
  }
}

function onBlur() {
  armed.value = -1
  // Let a click on a suggestion land before the list disappears.
  window.setTimeout(() => {
    open.value = false
  }, 120)
}

defineExpose({ focus: () => inputRef.value?.focus() })
</script>

<template>
  <div :class="classes">
    <div class="wx-tags-input__anchor" @click="inputRef?.focus()">
      <span
        v-for="(tag, index) in tags"
        :key="tag"
        class="wx-tags-input__tag"
        :class="{ 'is-armed': index === armed }"
      >
        {{ tag }}
        <button
          v-if="!field.disabled.value"
          class="wx-tags-input__remove"
          type="button"
          tabindex="-1"
          :aria-label="`Remove ${tag}`"
          @click.stop="remove(index)"
        >
          &#10005;
        </button>
      </span>

      <input
        :id="field.id.value"
        ref="inputRef"
        v-bind="$attrs"
        class="wx-tags-input__field"
        type="text"
        role="combobox"
        autocomplete="off"
        :value="text"
        :placeholder="placeholder"
        :disabled="field.disabled.value"
        :aria-label="ariaLabel"
        :aria-describedby="field.describedBy.value"
        :aria-invalid="field.status.value === 'error' || undefined"
        :aria-expanded="showList"
        @input="onInput"
        @keydown="onKeydown"
        @focus="open = true"
        @blur="onBlur"
      />

      <ul v-if="showList" class="wx-tags-input__list" role="listbox">
        <li
          v-for="(suggestion, index) in suggestions"
          :key="suggestion"
          class="wx-tags-input__option"
          :class="{ 'is-highlighted': index === highlighted }"
          role="option"
          :aria-selected="index === highlighted"
          @mousedown.prevent="add(suggestion)"
          @mousemove="highlighted = index"
        >
          {{ suggestion }}
        </li>
      </ul>
    </div>

    <select v-if="name" class="wx-sr-only" :name="name" multiple tabindex="-1" aria-hidden="true">
      <option v-for="tag in tags" :key="tag" :value="tag" selected>{{ tag }}</option>
    </select>
  </div>
</template>

<style scoped>
.wx-tags-input {
  display: block;
  width: 100%;
}

.wx-tags-input__anchor {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  box-sizing: border-box;
  min-height: var(--wx-size-control-md);
  padding: var(--wx-space-4) var(--wx-space-10);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-md);
  cursor: text;
  transition: border-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-tags-input--sm .wx-tags-input__anchor {
  min-height: var(--wx-size-control-sm);
  font-size: var(--wx-font-size-sm);
}

.wx-tags-input--lg .wx-tags-input__anchor {
  min-height: var(--wx-size-control-lg);
  font-size: var(--wx-font-size-lg);
}

.wx-tags-input__anchor:hover {
  border-color: var(--wx-border-strong);
}

.wx-tags-input__anchor:focus-within {
  border-color: var(--wx-border-focus);
}

.wx-tags-input--error .wx-tags-input__anchor {
  border-color: var(--wx-color-danger);
}

.wx-tags-input--success .wx-tags-input__anchor {
  border-color: var(--wx-color-success);
}

.wx-tags-input--warning .wx-tags-input__anchor {
  border-color: var(--wx-color-warning);
}

.wx-tags-input.is-disabled .wx-tags-input__anchor {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-tags-input__tag {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  padding: 2px var(--wx-space-6);
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-xs);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-tight);
}

.wx-tags-input__tag.is-armed {
  background: var(--wx-color-danger-soft);
  color: var(--wx-color-danger);
}

.wx-tags-input__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  background: transparent;
  border: none;
  color: currentcolor;
  font-size: var(--wx-font-size-xs);
  line-height: 1;
  opacity: 0.65;
  cursor: pointer;
}

.wx-tags-input__remove:hover {
  opacity: 1;
}

.wx-tags-input__field {
  flex: 1 1 80px;
  min-width: 0;
  padding: 0;
  background: transparent;
  border: none;
  outline: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
}

.wx-tags-input__field::placeholder {
  color: var(--wx-text-placeholder);
}

.wx-tags-input__list {
  position: absolute;
  top: calc(100% + var(--wx-space-4));
  left: 0;
  right: 0;
  z-index: var(--wx-z-index-dropdown);
  max-height: 220px;
  margin: 0;
  padding: var(--wx-space-4);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  list-style: none;
  overflow-y: auto;
}

.wx-tags-input__option {
  padding: var(--wx-space-6) var(--wx-space-10);
  border-radius: var(--wx-radius-xs);
  cursor: pointer;
}

.wx-tags-input__option.is-highlighted {
  background: var(--wx-bg-fill);
}
</style>
