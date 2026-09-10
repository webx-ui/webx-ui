import { computed, inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue'

export type ControlSize = 'sm' | 'md' | 'lg'
export type ControlStatus = 'default' | 'success' | 'warning' | 'error'

/** Field name -> messages, the shape Laravel returns on a 422. */
export type ValidationErrors = Record<string, string[]>

export interface FormContext {
  /** Server-side errors, keyed by field name. */
  errors: Ref<ValidationErrors>
  disabled: Ref<boolean>
  size: Ref<ControlSize>
  labelPosition: Ref<'top' | 'left'>
  labelWidth: Ref<string | undefined>
}

export interface FormItemContext {
  /** Id given to the control, so the label's `for` matches. */
  id: ComputedRef<string>
  /** Ids of the help and error nodes, for `aria-describedby`. */
  describedBy: ComputedRef<string | undefined>
  status: ComputedRef<ControlStatus>
  disabled: ComputedRef<boolean>
  size: ComputedRef<ControlSize | undefined>
  /** Set by grouped controls so the label points at the group, not one input. */
  registerLabelTarget: (id: string | undefined) => void
}

export const formContextKey: InjectionKey<FormContext> = Symbol('wx-form')
export const formItemContextKey: InjectionKey<FormItemContext> = Symbol('wx-form-item')

let uid = 0
export function useId(prefix = 'wx'): string {
  uid += 1
  return `${prefix}-${uid}`
}

export interface FormFieldProps {
  disabled?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
}

/**
 * Resolves what a control should render as, given its own props, the enclosing
 * `WxFormItem` and the enclosing `WxForm`. The control's own prop always wins;
 * `WxFormItem` beats `WxForm`.
 */
export function useFormField(props: FormFieldProps) {
  const form = inject(formContextKey, null)
  const item = inject(formItemContextKey, null)
  const fallbackId = useId('wx-control')

  const id = computed(() => props.id ?? item?.id.value ?? fallbackId)

  const disabled = computed(
    () => props.disabled || item?.disabled.value || form?.disabled.value || false,
  )

  const size = computed<ControlSize>(
    () => props.size ?? item?.size.value ?? form?.size.value ?? 'md',
  )

  const status = computed<ControlStatus>(() => {
    if (props.status && props.status !== 'default') return props.status
    return item?.status.value ?? 'default'
  })

  const describedBy = computed(() => item?.describedBy.value)

  return { id, disabled, size, status, describedBy, form, item }
}

/**
 * What a checkbox, radio or switch may carry. Deliberately narrower than `unknown`:
 * a `v-model` that hands back `unknown` is useless to the caller, and object values
 * would need identity comparison rather than `===`.
 */
export type ChoiceValue = string | number | boolean | null

/** Used by `WxCheckboxGroup` / `WxRadioGroup` to share state with their children. */
export interface ChoiceGroupContext<T> {
  name: ComputedRef<string | undefined>
  modelValue: Ref<T>
  disabled: ComputedRef<boolean>
  size: ComputedRef<ControlSize>
  toggle: (value: ChoiceValue, checked: boolean) => void
}

export const checkboxGroupKey: InjectionKey<ChoiceGroupContext<ChoiceValue[]>> =
  Symbol('wx-checkbox-group')
export const radioGroupKey: InjectionKey<ChoiceGroupContext<ChoiceValue | undefined>> =
  Symbol('wx-radio-group')

export function provideFormItem(context: FormItemContext): void {
  provide(formItemContextKey, context)
}
