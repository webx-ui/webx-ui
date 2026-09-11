<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  AutocompleteAnchor,
  AutocompleteContent,
  AutocompleteEmpty,
  AutocompleteInput,
  AutocompleteItem,
  AutocompletePortal,
  AutocompleteRoot,
  AutocompleteViewport,
} from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import { useFormField } from '../../composables/useFormField'
import type { AutocompleteEmits, AutocompleteOption, AutocompleteProps } from './types'
import { useControlAttrs } from '../../composables/useControlAttrs'

defineOptions({ name: 'WxAutocomplete', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<AutocompleteProps>(), {
  options: () => [],
  remote: false,
  debounce: 300,
  loading: false,
  loadingText: 'Searching…',
  emptyText: 'Nothing found',
  minLength: 0,
  placeholder: undefined,
  clearable: false,
  openOnFocus: true,
  teleport: true,
  disabled: undefined,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<AutocompleteEmits>()

/** The model is the text in the field — an autocomplete suggests, it does not constrain. */
const model = defineModel<string>({ default: '' })

const field = useFormField(props)
const open = ref(false)

let debounceTimer: ReturnType<typeof setTimeout> | undefined
/** Set while a pick is being applied, so choosing a suggestion does not search for it. */
let picking = false

function cancelSearch() {
  if (debounceTimer === undefined) return
  clearTimeout(debounceTimer)
  debounceTimer = undefined
}

watch(model, (value) => {
  emit('change', value)

  if (picking) {
    picking = false
    cancelSearch()
    return
  }

  cancelSearch()
  if (value.length < props.minLength) {
    open.value = false
    return
  }

  debounceTimer = setTimeout(() => {
    debounceTimer = undefined
    emit('search', value)
  }, props.debounce)
})

watch(open, (value) => {
  if (value) emit('open')
  else emit('close')
})

onBeforeUnmount(cancelSearch)

const showClear = computed(() => props.clearable && !field.disabled.value && model.value.length > 0)

/** Nothing to drop down yet: below `min-length`, or still waiting on the first response. */
const hasList = computed(
  () => props.options.length > 0 || props.loading || model.value.length >= props.minLength,
)

const classes = computed(() => [
  'wx-autocomplete',
  `wx-autocomplete--${field.size.value}`,
  {
    [`wx-autocomplete--${field.status.value}`]: field.status.value !== 'default',
    'is-open': open.value,
    'is-disabled': field.disabled.value,
  },
])

function onSelect(option: AutocompleteOption) {
  picking = true
  emit('select', option)
}

function clear() {
  picking = true
  model.value = ''
  open.value = false
  emit('clear')
}
</script>

<template>
  <autocomplete-root
    v-model="model"
    v-model:open="open"
    as="div"
    :class="classes"
    v-bind="rootAttrs"
    :disabled="field.disabled.value"
    :name="name"
    :ignore-filter="remote"
    :open-on-focus="openOnFocus"
    :open-on-click="openOnFocus"
  >
    <autocomplete-anchor class="wx-autocomplete__anchor" as="div">
      <span v-if="$slots.prefix" class="wx-autocomplete__affix">
        <slot name="prefix" />
      </span>

      <autocomplete-input
        :id="field.id.value"
        v-bind="controlAttrs"
        class="wx-autocomplete__input"
        :placeholder="placeholder"
        :disabled="field.disabled.value"
        :aria-label="ariaLabel"
        :aria-describedby="field.describedBy.value"
        :aria-invalid="field.status.value === 'error' || undefined"
      />

      <wx-icon v-if="loading" class="wx-autocomplete__spinner" name="loader" spin />

      <button
        v-else-if="showClear"
        class="wx-autocomplete__clear"
        type="button"
        tabindex="-1"
        aria-label="Clear"
        @click="clear"
      >
        <wx-icon name="close" />
      </button>

      <span v-if="$slots.suffix" class="wx-autocomplete__affix">
        <slot name="suffix" />
      </span>
    </autocomplete-anchor>

    <autocomplete-portal :disabled="!teleport">
      <autocomplete-content
        v-if="hasList"
        class="wx-autocomplete__content"
        position="popper"
        :side-offset="4"
      >
        <autocomplete-viewport class="wx-autocomplete__viewport">
          <div v-if="loading && options.length === 0" class="wx-autocomplete__status">
            {{ loadingText }}
          </div>
          <autocomplete-empty v-else class="wx-autocomplete__status">
            {{ emptyText }}
          </autocomplete-empty>

          <autocomplete-item
            v-for="option in options"
            :key="option.value"
            class="wx-autocomplete__option"
            :value="option.value"
            :disabled="option.disabled"
            @select="onSelect(option)"
          >
            <slot name="option" :option="option">
              <span class="wx-autocomplete__option-label">{{ option.label ?? option.value }}</span>
              <span v-if="option.description" class="wx-autocomplete__option-description">
                {{ option.description }}
              </span>
            </slot>
          </autocomplete-item>
        </autocomplete-viewport>
      </autocomplete-content>
    </autocomplete-portal>
  </autocomplete-root>
</template>

<style scoped>
.wx-autocomplete {
  display: block;
  width: 100%;
}

.wx-autocomplete__anchor {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  box-sizing: border-box;
  height: var(--wx-size-control-md);
  padding: 0 var(--wx-space-10) 0 var(--wx-space-12);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-md);
  transition: border-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-autocomplete--sm .wx-autocomplete__anchor {
  height: var(--wx-size-control-sm);
  font-size: var(--wx-font-size-sm);
}

.wx-autocomplete--lg .wx-autocomplete__anchor {
  height: var(--wx-size-control-lg);
  font-size: var(--wx-font-size-lg);
}

.wx-autocomplete__anchor:hover {
  border-color: var(--wx-border-strong);
}

.wx-autocomplete.is-open .wx-autocomplete__anchor,
.wx-autocomplete__anchor:focus-within {
  border-color: var(--wx-border-focus);
}

.wx-autocomplete--error .wx-autocomplete__anchor,
.wx-autocomplete--error.is-open .wx-autocomplete__anchor {
  border-color: var(--wx-color-danger);
}

.wx-autocomplete--success .wx-autocomplete__anchor {
  border-color: var(--wx-color-success);
}

.wx-autocomplete--warning .wx-autocomplete__anchor {
  border-color: var(--wx-color-warning);
}

.wx-autocomplete.is-disabled .wx-autocomplete__anchor {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-autocomplete__input {
  flex: 1 1 auto;
  min-width: 0;
  height: 100%;
  padding: 0;
  background: transparent;
  border: none;
  outline: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
}

.wx-autocomplete__input::placeholder {
  color: var(--wx-text-placeholder);
}

.wx-autocomplete__input:disabled {
  cursor: not-allowed;
}

.wx-autocomplete__affix,
.wx-autocomplete__spinner {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  color: var(--wx-text-muted);
}

.wx-autocomplete__clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 20px;
  height: 20px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-autocomplete__clear:hover {
  color: var(--wx-text-default);
}
</style>

<style>
/* The list is teleported, so its styles cannot be scoped to the component. */
.wx-autocomplete__content {
  /* One layer for every floating panel, so the one opened last is the one on top. */
  z-index: var(--wx-z-index-popover);
  box-sizing: border-box;
  width: var(--reka-combobox-trigger-width);
  max-height: 300px;
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

.wx-autocomplete__viewport {
  max-height: 292px;
  overflow-y: auto;
}

.wx-autocomplete__option {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--wx-space-6) var(--wx-space-10);
  border-radius: var(--wx-radius-xs);
  cursor: pointer;
  user-select: none;
}

.wx-autocomplete__option[data-highlighted] {
  background: var(--wx-bg-fill);
  outline: none;
}

.wx-autocomplete__option[data-disabled] {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-autocomplete__option-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-autocomplete__option-description {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-normal);
}

.wx-autocomplete__status {
  padding: var(--wx-space-10);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: center;
}
</style>
