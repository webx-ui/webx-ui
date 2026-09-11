<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ComboboxAnchor,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxPortal,
  ComboboxRoot,
  ComboboxTrigger,
  ComboboxViewport,
} from 'reka-ui'
import { useFormField } from '../../composables/useFormField'
import type { SelectEmits, SelectModelValue, SelectProps, SelectValue } from './types'

defineOptions({ name: 'WxSelect', inheritAttrs: false })

const props = withDefaults(defineProps<SelectProps>(), {
  options: () => [],
  multiple: false,
  filterable: false,
  clearable: false,
  placeholder: undefined,
  emptyText: 'Nothing found',
  teleport: true,
  disabled: undefined,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<SelectEmits>()

const model = defineModel<SelectModelValue>({ default: null })

const field = useFormField(props)
const open = ref(false)

/**
 * The text in the search field. Controlled here rather than left to the combobox:
 * with `multiple` the input has no display value to fall back on and renders the
 * model itself, so picking two tags leaves "news,releases" sitting in the box.
 */
const searchText = ref('')

/** Reka wants an array for multiple and a bare value otherwise. */
const selection = computed({
  get: () => (props.multiple ? ((model.value as SelectValue[]) ?? []) : (model.value ?? null)),
  set: (value) => {
    model.value = value as SelectModelValue
    emit('change', value as SelectModelValue)
  },
})

const labelOf = (value: SelectValue) =>
  props.options.find((option) => option.value === value)?.label ?? String(value)

const selectedOptions = computed(() => {
  if (!props.multiple) return []
  return ((model.value as SelectValue[]) ?? []).map((value) => ({ value, label: labelOf(value) }))
})

const singleLabel = computed(() => {
  if (props.multiple) return ''
  const value = model.value as SelectValue | null
  return value === null || value === undefined ? '' : labelOf(value)
})

const hasSelection = computed(() =>
  props.multiple
    ? selectedOptions.value.length > 0
    : model.value !== null && model.value !== undefined && model.value !== '',
)

const showClear = computed(() => props.clearable && !field.disabled.value && hasSelection.value)

const classes = computed(() => [
  'wx-select',
  `wx-select--${field.size.value}`,
  {
    [`wx-select--${field.status.value}`]: field.status.value !== 'default',
    'is-open': open.value,
    'is-disabled': field.disabled.value,
    'is-multiple': props.multiple,
  },
])

/** A pick clears the search in multiple mode and shows the label in single mode. */
watch(
  () => model.value,
  () => {
    searchText.value = props.multiple ? '' : singleLabel.value
  },
  { immediate: true },
)

watch(open, (value) => {
  if (value) {
    emit('open')
    return
  }
  searchText.value = props.multiple ? '' : singleLabel.value
  emit('close')
})

/** Clicking the field is how everyone expects a select to open. */
function openList() {
  if (!field.disabled.value) open.value = true
}

/**
 * What a filterable field shows once a value is picked. Without it the input falls
 * back to the raw value, so choosing "Maria Kovalenko" leaves "12" in the box.
 */
function displayValue(value: unknown): string {
  return value === null || value === undefined ? '' : labelOf(value as SelectValue)
}

/**
 * Reka filters the list itself and keeps no public search term, so the value is read
 * straight off the input — that is what a caller loading options from a backend needs.
 */
function onSearch(event: Event) {
  emit('search', (event.target as HTMLInputElement).value)
}

function clear() {
  model.value = props.multiple ? [] : null
  emit('change', model.value)
  emit('clear')
}

function removeTag(value: SelectValue) {
  const next = ((model.value as SelectValue[]) ?? []).filter((item) => item !== value)
  model.value = next
  emit('change', next)
}
</script>

<template>
  <combobox-root
    v-model="selection"
    v-model:open="open"
    :multiple="multiple"
    :disabled="field.disabled.value"
    :name="name"
    :ignore-filter="!filterable"
    :open-on-click="true"
    :class="classes"
    as="div"
  >
    <combobox-anchor class="wx-select__anchor" as="div" @click="openList">
      <div class="wx-select__value">
        <template v-if="multiple">
          <span
            v-for="option in selectedOptions"
            :key="String(option.value)"
            class="wx-select__tag"
          >
            {{ option.label }}
            <button
              v-if="!field.disabled.value"
              class="wx-select__tag-remove"
              type="button"
              tabindex="-1"
              :aria-label="`Remove ${option.label}`"
              @click.stop="removeTag(option.value)"
            >
              &#10005;
            </button>
          </span>
        </template>

        <combobox-input
          v-if="filterable"
          :id="field.id.value"
          v-bind="$attrs"
          v-model="searchText"
          class="wx-select__input"
          :placeholder="placeholder"
          :aria-label="ariaLabel"
          :aria-describedby="field.describedBy.value"
          :aria-invalid="field.status.value === 'error' || undefined"
          :display-value="multiple ? undefined : displayValue"
          auto-focus
          @input="onSearch"
        />

        <template v-else>
          <span v-if="!multiple && hasSelection" class="wx-select__single">{{ singleLabel }}</span>
          <span v-else-if="!hasSelection" class="wx-select__placeholder">{{ placeholder }}</span>
        </template>
      </div>

      <button
        v-if="showClear"
        class="wx-select__clear"
        type="button"
        tabindex="-1"
        aria-label="Clear"
        @click.stop="clear"
      >
        &#10005;
      </button>

      <combobox-trigger
        :id="filterable ? undefined : field.id.value"
        class="wx-select__toggle"
        :aria-label="ariaLabel"
        :aria-describedby="field.describedBy.value"
      >
        <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path
            d="m4 6 4 4 4-4"
            stroke="currentColor"
            stroke-width="1.6"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </combobox-trigger>
    </combobox-anchor>

    <combobox-portal :disabled="!teleport">
      <combobox-content class="wx-select__content" position="popper" :side-offset="4">
        <combobox-viewport class="wx-select__viewport">
          <combobox-empty class="wx-select__empty">{{ emptyText }}</combobox-empty>

          <slot>
            <combobox-item
              v-for="option in options"
              :key="String(option.value)"
              class="wx-select__option"
              :value="option.value"
              :disabled="option.disabled"
            >
              <span class="wx-select__option-label">{{ option.label }}</span>
              <combobox-item-indicator class="wx-select__check">
                <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path
                    d="m3.5 8.5 3 3 6-6"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </combobox-item-indicator>
            </combobox-item>
          </slot>
        </combobox-viewport>
      </combobox-content>
    </combobox-portal>
  </combobox-root>
</template>

<style scoped>
.wx-select {
  display: block;
  width: 100%;
}

.wx-select__anchor {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  box-sizing: border-box;
  min-height: var(--wx-size-control-md);
  padding: 0 var(--wx-space-6) 0 var(--wx-space-12);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-md);
  cursor: pointer;
  transition: border-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-select--sm .wx-select__anchor {
  min-height: var(--wx-size-control-sm);
  font-size: var(--wx-font-size-sm);
}

.wx-select--lg .wx-select__anchor {
  min-height: var(--wx-size-control-lg);
  font-size: var(--wx-font-size-lg);
}

.wx-select__anchor:hover {
  border-color: var(--wx-border-strong);
}

.wx-select.is-open .wx-select__anchor {
  border-color: var(--wx-border-focus);
}

.wx-select--error .wx-select__anchor,
.wx-select--error.is-open .wx-select__anchor {
  border-color: var(--wx-color-danger);
}

.wx-select--success .wx-select__anchor {
  border-color: var(--wx-color-success);
}

.wx-select--warning .wx-select__anchor {
  border-color: var(--wx-color-warning);
}

.wx-select.is-disabled .wx-select__anchor {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-select__value {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  flex: 1 1 auto;
  min-width: 0;
  padding: var(--wx-space-4) 0;
}

.wx-select__single,
.wx-select__placeholder {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-select__placeholder {
  color: var(--wx-text-placeholder);
}

.wx-select__input {
  flex: 1 1 60px;
  min-width: 0;
  padding: 0;
  background: transparent;
  border: none;
  outline: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
}

.wx-select__input::placeholder {
  color: var(--wx-text-placeholder);
}

.wx-select__tag {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  padding: 2px var(--wx-space-6);
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-xs);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-tight);
}

.wx-select__tag-remove,
.wx-select__clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: 1;
  cursor: pointer;
}

.wx-select__tag-remove:hover,
.wx-select__clear:hover {
  color: var(--wx-text-default);
}

.wx-select__clear {
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
}

.wx-select__toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 24px;
  height: 24px;
  padding: 0;
  background: transparent;
  border: none;
  color: var(--wx-text-muted);
  cursor: inherit;
  transition: transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-select__toggle svg {
  width: 16px;
  height: 16px;
}

.wx-select.is-open .wx-select__toggle {
  transform: rotate(180deg);
}
</style>

<style>
/* The list is teleported, so its styles cannot be scoped to the component. */
.wx-select__content {
  /* One layer for every floating panel, so the one opened last is the one on top. */
  z-index: var(--wx-z-index-popover);
  box-sizing: border-box;
  width: var(--reka-combobox-trigger-width);
  max-height: 280px;
  padding: var(--wx-space-4);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-md);
  overflow: hidden;
}

.wx-select__viewport {
  max-height: 272px;
  overflow-y: auto;
}

.wx-select__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  padding: var(--wx-space-6) var(--wx-space-10);
  border-radius: var(--wx-radius-xs);
  cursor: pointer;
  user-select: none;
}

.wx-select__option[data-highlighted] {
  background: var(--wx-bg-fill);
  outline: none;
}

.wx-select__option[data-state='checked'] {
  color: var(--wx-color-primary);
  font-weight: var(--wx-font-weight-medium);
}

.wx-select__option[data-disabled] {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-select__option-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-select__check {
  display: inline-flex;
  flex: 0 0 auto;
  color: var(--wx-color-primary);
}

.wx-select__check svg {
  width: 16px;
  height: 16px;
}

.wx-select__empty {
  padding: var(--wx-space-10);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: center;
}
</style>
