import { computed, defineComponent, inject, provide, type PropType } from 'vue'
import { formContextKey, type ValidationErrors } from '../composables/useFormField'

/**
 * A part of a form that answers to its own errors: the form around it, with `errors` replaced.
 *
 * A row of a repeater is a small form inside the big one, and its fields are named as the row
 * names them — `title`, `url`. `WxFormItem` looks its error up by that name on the form, so
 * without a scope a row's `title` shows the error of the record's own `title`, in every row at
 * once, and the row that was actually refused (`articles.2.title.en`) shows nothing.
 *
 * Outside a form there is nothing to narrow, and the slot renders as it is.
 */
export default defineComponent({
  name: 'WxFormScope',
  props: {
    errors: { type: Object as PropType<ValidationErrors>, required: true },
  },
  setup(props, { slots }) {
    const form = inject(formContextKey, null)

    if (form !== null) {
      provide(formContextKey, { ...form, errors: computed(() => props.errors) })
    }

    return () => slots.default?.()
  },
})
