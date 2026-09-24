<script setup lang="ts">
import { computed, markRaw, provide, toRaw, watch } from 'vue'
import { WxForm } from '@webx-ui/core'
import { applyPatch } from './patch'
import { coreTypes } from './registry'
import { keyAsIs, screenErrorsKey, WxScreenNodes, type RenderContext } from './render'
import type {
  Patch,
  PatchError,
  ScreenModel,
  ScreenNode,
  Translate,
  TypeRegistry,
  ValidationErrors,
} from './types'

defineOptions({ name: 'WxScreenRenderer' })

const props = withDefaults(
  defineProps<{
    /** The tree to draw — a module's screen, already patched by the server. */
    root: ScreenNode[]
    /** Client-side patch applied on top, in order. */
    patch?: Patch
    /** Server-side validation errors, keyed by field name; shown under the fields. */
    errors?: ValidationErrors
    /** Project types, merged over the core ones. */
    types?: TypeRegistry
    /** Turns `trans::` strings into words. Without it the key shows. */
    translate?: Translate
    /** Decides `can`. Without it every node is allowed. */
    can?: (permission: string) => boolean
    disabled?: boolean
    labelPosition?: 'top' | 'left'
    labelWidth?: string
    size?: 'sm' | 'md' | 'lg'
  }>(),
  {
    patch: () => [],
    errors: undefined,
    types: undefined,
    translate: undefined,
    can: undefined,
    disabled: false,
    labelPosition: undefined,
    labelWidth: undefined,
    size: undefined,
  },
)

const emit = defineEmits<{
  /** Operations that could not be applied. Also reported to the console. */
  patchError: [errors: PatchError[]]
}>()

const model = defineModel<ScreenModel>({ default: () => ({}) })

const applied = computed(() => applyPatch(props.root, props.patch))

/** Every field name on the screen, however deep — a repeater is one field, not its items. */
function names(nodes: ScreenNode[]): string[] {
  return nodes.flatMap((node) =>
    node.name === undefined ? names(node.children ?? []) : [node.name],
  )
}

/**
 * The refusal, under the names the fields answer to.
 *
 * Laravel names the language that failed — `slug.en` — and `WxFormItem` looks its error up by
 * the field's own name, so a refused translated field showed nothing at all: the save failed and
 * every field looked fine. A key is matched against the field names rather than cut at its first
 * dot, because a name may carry a dot of its own (`general.project-name`, CLAUDE.md §4).
 */
const fieldErrors = computed<ValidationErrors | undefined>(() => {
  if (props.errors === undefined) return undefined

  const fields = names(applied.value.root)
  const out: ValidationErrors = { ...props.errors }

  for (const [key, messages] of Object.entries(props.errors)) {
    const field = fields.find((name) => key.startsWith(`${name}.`))

    if (field !== undefined && out[field] === undefined) out[field] = messages
  }

  return out
})

// For the nodes that act on a refusal rather than only draw it — the tabs (`ScreenTabs.vue`).
provide(screenErrorsKey, fieldErrors)

watch(
  () => applied.value.errors,
  (errors) => {
    for (const error of errors) {
      console.error(`[webx-ui/schema] patch[${error.index}] ${error.op.op}: ${error.message}`)
    }
    emit('patchError', errors)
  },
  { immediate: true },
)

/**
 * A registry kept in a `ref` arrives as a reactive proxy, and Vue warns about a
 * component that is one; the raw component is what `h()` wants anyway.
 */
const registry = computed<TypeRegistry>(() => {
  const merged: TypeRegistry = {}
  for (const [type, entry] of Object.entries({ ...coreTypes, ...props.types })) {
    merged[type] = { ...entry, component: markRaw(toRaw(entry.component)) }
  }
  return merged
})

const context = computed<RenderContext>(() => ({
  types: registry.value,
  model: model.value,
  update: (name, value) => {
    model.value = { ...model.value, [name]: value }
  },
  translate: props.translate ?? keyAsIs,
  can: props.can ?? (() => true),
}))

defineExpose({
  /** The tree after the patch — what is actually on screen. */
  tree: computed(() => applied.value.root),
})
</script>

<template>
  <wx-form
    class="wx-screen"
    :errors="fieldErrors"
    :disabled="disabled"
    :label-position="labelPosition"
    :label-width="labelWidth"
    :size="size"
  >
    <wx-screen-nodes :nodes="applied.root" :context="context" />
  </wx-form>
</template>

<style>
/*
 * A column is a stack of fields, like a card's body. The form's own gap reaches only its
 * direct children, so two fields dropped into one `wx-col` stood flush: the label of the
 * second read as the hint of the first. Same step as the form and the card.
 */
.wx-screen__col {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
}

/*
 * A number is a few digits, and a field as wide as a title put its − and + a whole line apart,
 * with the digits alone in the middle. The core's own number keeps `width: 100%` — a filter row
 * or a table cell decides its width — so the cap is the screen's.
 */
.wx-screen .wx-input-number {
  max-width: 240px;
}

/* Global on purpose: the placeholder is created by a render function, outside any scope. */
.wx-screen__unknown {
  padding: var(--wx-space-8) var(--wx-space-12);
  border: 1px dashed var(--wx-color-danger);
  border-radius: var(--wx-radius-control);
  background: var(--wx-color-danger-soft);
  color: var(--wx-color-danger);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
}
</style>
