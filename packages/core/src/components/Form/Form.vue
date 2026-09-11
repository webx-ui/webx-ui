<script setup lang="ts">
import { computed, provide, toRef } from 'vue'
import { formContextKey, type ValidationErrors } from '../../composables/useFormField'
import type { FormEmits, FormProps } from './types'

defineOptions({ name: 'WxForm' })

const props = withDefaults(defineProps<FormProps>(), {
  errors: undefined,
  disabled: false,
  size: 'md',
  labelPosition: 'top',
  labelWidth: undefined,
  gap: 'md',
})

const emit = defineEmits<FormEmits>()

const errors = computed<ValidationErrors>(() => props.errors ?? {})

provide(formContextKey, {
  errors,
  disabled: toRef(props, 'disabled'),
  size: toRef(props, 'size'),
  labelPosition: toRef(props, 'labelPosition'),
  labelWidth: toRef(props, 'labelWidth'),
})

/** Native submit is always prevented: forms here talk to a data adapter, not a URL. */
function onSubmit(event: SubmitEvent) {
  event.preventDefault()
  emit('submit', event)
}

function onReset(event: Event) {
  emit('reset', event)
}
</script>

<template>
  <form
    :class="['wx-form', `wx-form--gap-${gap}`, `wx-form--label-${labelPosition}`]"
    novalidate
    @submit="onSubmit"
    @reset="onReset"
  >
    <slot />
  </form>
</template>

<style scoped>
.wx-form {
  display: flex;
  flex-direction: column;
}

.wx-form--gap-sm {
  gap: var(--wx-space-8);
}

.wx-form--gap-md {
  gap: var(--wx-space-24);
}

.wx-form--gap-lg {
  gap: var(--wx-space-32);
}
</style>
