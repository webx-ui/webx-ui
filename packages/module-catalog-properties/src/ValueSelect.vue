<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { toast, WxSelect, type SelectModelValue, type SelectOption } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createPropertiesApi } from './api'
import { wordsIn } from './format'
import { NAMESPACE } from './i18n'
import type { PropertyRow, PropertyValue } from './types'

/**
 * A value of a flat reference book in the product form (§7.2): a combobox that searches the book
 * on the server — a book of three hundred colours is not an option list sent with the form — and
 * offers «Create "…"» for what it does not find, to whoever may write the catalogue.
 *
 * The chosen values are named by their own request, once: the form holds ids, and a combobox
 * showing «37» where «Black» belongs is the one thing this must not do.
 */
defineOptions({ name: 'WxCatalogPropertyValueSelect' })

const props = withDefaults(
  defineProps<{ property: PropertyRow; disabled?: boolean; ariaLabel?: string }>(),
  { disabled: false, ariaLabel: undefined },
)

const value = defineModel<number | number[] | null>({ default: null })

const admin = useAdmin()
const api = createPropertiesApi(admin)
const t = useTranslate(NAMESPACE)
const message = useErrorText()

/** The one option that is not a value: «Create "…"». Values are positive ids. */
const CREATE = -1

const locale = computed(() => admin.i18n.state.locale)
const canCreate = computed(() => admin.can('catalog.manage'))

const known = ref(new Map<number, string>())
const found = ref<number[]>([])
const term = ref('')
const creating = ref(false)

const chosen = computed<number[]>(() =>
  value.value === null ? [] : Array.isArray(value.value) ? value.value : [value.value],
)

function remember(values: PropertyValue[]): number[] {
  const next = new Map(known.value)

  for (const one of values) next.set(one.id, wordsIn(one.title, locale.value, `#${one.id}`))
  known.value = next

  return values.map((one) => one.id)
}

const exact = computed(() => {
  const typed = term.value.trim().toLocaleLowerCase(locale.value)

  return [...known.value.values()].some((label) => label.toLocaleLowerCase(locale.value) === typed)
})

const options = computed<SelectOption[]>(() => {
  const ids = [...new Set([...chosen.value, ...found.value])]
  const list: SelectOption[] = ids.map((id) => ({
    value: id,
    label: known.value.get(id) ?? `#${id}`,
  }))

  if (canCreate.value && term.value.trim() !== '' && !exact.value) {
    list.push({ value: CREATE, label: t('panel.value-create', { name: term.value.trim() }) })
  }

  return list
})

/* The chosen ones by name — asked only for ids the combobox cannot name yet. */
watch(
  chosen,
  async (ids) => {
    const missing = ids.filter((id) => !known.value.has(id))

    if (missing.length === 0) return

    try {
      remember(await api.valuesById(props.property.id, missing))
    } catch {
      // The ids stay and show as they are; the value is not lost for want of its name.
    }
  },
  { immediate: true },
)

let timer: ReturnType<typeof setTimeout> | undefined
let asked = 0

async function search(text: string): Promise<void> {
  const ticket = ++asked

  try {
    const answer = await api.searchValues(props.property.id, text.trim(), 30)

    if (ticket === asked) found.value = remember(answer)
  } catch {
    if (ticket === asked) found.value = []
  }
}

function typed(text: string): void {
  term.value = text
  clearTimeout(timer)
  timer = setTimeout(() => void search(text), 200)
}

onBeforeUnmount(() => clearTimeout(timer))

async function create(): Promise<number | null> {
  const name = term.value.trim()

  if (name === '' || creating.value) return null

  creating.value = true

  try {
    const [id] = remember([
      await api.createValue(props.property.id, { title: { [locale.value]: name } }),
    ])

    term.value = ''

    return id ?? null
  } catch (error) {
    toast.danger(message(error))

    return null
  } finally {
    creating.value = false
  }
}

async function pick(next: SelectModelValue): Promise<void> {
  const list = next === null ? [] : Array.isArray(next) ? next.map(Number) : [Number(next)]

  if (list.includes(CREATE)) {
    const made = await create()
    const rest = list.filter((id) => id !== CREATE)

    if (made !== null) rest.push(made)

    value.value = props.property.is_multiple ? rest : (made ?? chosen.value[0] ?? null)

    return
  }

  value.value = props.property.is_multiple ? list : (list[0] ?? null)
}
</script>

<template>
  <wx-select
    :model-value="props.property.is_multiple ? chosen : (chosen[0] ?? null)"
    :options="options"
    :multiple="props.property.is_multiple"
    :disabled="props.disabled || creating"
    :aria-label="props.ariaLabel"
    :placeholder="t('panel.value-pick')"
    :empty-text="t('panel.nothing-found')"
    filterable
    clearable
    @update:model-value="pick"
    @search="typed"
    @open="search(term)"
  />
</template>
