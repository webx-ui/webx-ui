<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import {
  formContextKey,
  provideFormItem,
  useId,
  type ControlSize,
  type ControlStatus,
} from '../../composables/useFormField'
import type { FormItemProps } from './types'

defineOptions({ name: 'WxFormItem' })

const props = withDefaults(defineProps<FormItemProps>(), {
  label: undefined,
  name: undefined,
  required: false,
  error: undefined,
  help: undefined,
  disabled: false,
  size: undefined,
  labelWidth: undefined,
  reserveErrorSpace: false,
})

const form = inject(formContextKey, null)

const baseId = useId('wx-field')
const errorId = `${baseId}-error`
const helpId = `${baseId}-help`

/**
 * Grouped controls (checkbox and radio groups) cannot be targeted by `for`,
 * so they register themselves and the label becomes a plain span instead.
 */
const labelMode = ref<'control' | 'group'>('control')
const controlId = ref<string>(baseId)

function registerLabelTarget(id: string | undefined) {
  if (id === undefined) {
    labelMode.value = 'group'
    return
  }
  labelMode.value = 'control'
  controlId.value = id
}

const messages = computed<string[]>(() => {
  if (props.error !== undefined) {
    return Array.isArray(props.error) ? props.error : [props.error]
  }
  if (props.name && form) return form.errors.value[props.name] ?? []
  return []
})

const hasError = computed(() => messages.value.length > 0)
const status = computed<ControlStatus>(() => (hasError.value ? 'error' : 'default'))

/**
 * Only ever names nodes that are actually rendered — the help text gives way to the
 * error, and a dangling `aria-describedby` is worse than none.
 */
const describedBy = computed(() => {
  if (hasError.value) return errorId
  return props.help ? helpId : undefined
})

const size = computed<ControlSize | undefined>(() => props.size)
const disabled = computed(() => props.disabled)

const labelWidth = computed(() => props.labelWidth ?? form?.labelWidth.value)
const isInline = computed(() => form?.labelPosition.value === 'left')

provideFormItem({
  id: computed(() => baseId),
  describedBy,
  status,
  disabled,
  size,
  registerLabelTarget,
})

defineExpose({ messages, hasError })
</script>

<template>
  <div
    :class="[
      'wx-form-item',
      { 'wx-form-item--inline': isInline, 'is-error': hasError, 'is-required': required },
    ]"
    :style="isInline && labelWidth ? { '--wx-form-item-label-width': labelWidth } : undefined"
  >
    <component
      :is="labelMode === 'group' ? 'span' : 'label'"
      v-if="label || $slots.label"
      :id="`${baseId}-label`"
      class="wx-form-item__label"
      :for="labelMode === 'group' ? undefined : controlId"
    >
      <slot name="label">{{ label }}</slot>
      <span v-if="required" class="wx-form-item__required" aria-hidden="true">*</span>
    </component>

    <div class="wx-form-item__control">
      <slot />

      <p v-if="hasError" :id="errorId" class="wx-form-item__error" role="alert">
        {{ messages[0] }}
      </p>
      <p v-else-if="help" :id="helpId" class="wx-form-item__help">{{ help }}</p>
      <p v-else-if="reserveErrorSpace" class="wx-form-item__error" aria-hidden="true">&nbsp;</p>
    </div>
  </div>
</template>

<style scoped>
.wx-form-item {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-form-item--inline {
  flex-direction: row;
  align-items: flex-start;
  gap: var(--wx-space-16);
}

.wx-form-item--inline .wx-form-item__label {
  flex: 0 0 auto;
  width: var(--wx-form-item-label-width, 160px);
  /* Line the label up with the first line of a control's text. */
  padding-top: calc((var(--wx-size-control-md) - 1lh) / 2);
}

.wx-form-item--inline .wx-form-item__control {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-form-item__label {
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-normal);
}

.wx-form-item__required {
  margin-left: var(--wx-space-2);
  color: var(--wx-color-danger);
}

.wx-form-item__control {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-form-item__error,
.wx-form-item__help {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

.wx-form-item__error {
  color: var(--wx-color-danger);
}

.wx-form-item__help {
  color: var(--wx-text-muted);
}
</style>
