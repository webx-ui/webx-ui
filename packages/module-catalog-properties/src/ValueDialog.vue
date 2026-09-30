<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import {
  useModal,
  WxButton,
  WxColorPicker,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { openMediaPicker } from '@webx-ui/module-media'
import { createPropertiesApi, type ValueInput } from './api'
import { NAMESPACE } from './i18n'
import type { PropertyValue } from './types'

/**
 * One value of a reference book: its name and its slug in every language the site publishes in —
 * the slug is the value in a filter's address, `/laptops/color_black/`, and is made of the name
 * where it is left empty (§3.4) — and its colour and its picture where the property has them. New
 * under `parent` when there is no `value`.
 *
 * The colour is data, not a style: the site's template decides how to draw it (§2). The picture is
 * one of the media library's, and wins over the colour on the storefront's swatch.
 */
defineOptions({ name: 'WxCatalogPropertyValueDialog' })

const props = withDefaults(
  defineProps<{
    property: number
    value?: PropertyValue | null
    parent?: number | null
    title: string
    color?: boolean
    image?: boolean
  }>(),
  { value: null, parent: null, color: false, image: false },
)

const { open, resolve, dismiss } = useModal<PropertyValue>()

const api = createPropertiesApi(useAdmin())
const t = useTranslate(NAMESPACE)

const words = (map: PropertyValue['title'] | undefined): LocalizedValue =>
  map && !Array.isArray(map) ? { ...map } : {}

const name = ref<LocalizedValue>(words(props.value?.title))
const slug = ref<LocalizedValue>(words(props.value?.slug))
const color = ref<string | null>(props.value?.color ?? null)
const picture = ref<{ id: number; url: string } | null>(
  props.value?.image
    ? { id: props.value.image.id, url: props.value.image.thumb ?? props.value.image.url }
    : null,
)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

function errorOf(key: string): string | undefined {
  const found = Object.keys(errors.value).find((one) => one === key || one.startsWith(`${key}.`))

  return found === undefined ? undefined : errors.value[found]?.[0]
}

const written = () => Object.values(name.value).some((line) => line.trim() !== '')

async function pick(): Promise<void> {
  const file = await openMediaPicker({ accept: 'image' })

  if (file) picture.value = { id: file.id, url: file.thumb ?? file.url }
}

async function submit(): Promise<void> {
  if (!written() || saving.value) return

  saving.value = true
  errors.value = {}

  // What the property does not have is not sent: a value keeps its colour while the switch is off,
  // and turning it back on brings the colour back.
  const input: ValueInput = { title: name.value, slug: slug.value }

  if (props.color) input.color = color.value
  if (props.image) input.image_id = picture.value?.id ?? null

  try {
    resolve(
      props.value
        ? await api.saveValue(props.property, props.value.id, input)
        : await api.createValue(props.property, { ...input, parent_id: props.parent }),
    )
  } catch (error) {
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="props.title" :width="480">
    <wx-form-item :label="t('value.title')" :error="errorOf('title')" required>
      <div ref="field">
        <wx-input v-model="name" localized :aria-label="t('value.title')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <wx-form-item :label="t('value.slug')" :help="t('panel.slug-help')" :error="errorOf('slug')">
      <wx-input v-model="slug" localized :aria-label="t('value.slug')" @keyup.enter="submit" />
    </wx-form-item>

    <wx-form-item v-if="props.color" :label="t('value.color')" :error="errorOf('color')">
      <wx-color-picker v-model="color" clearable :aria-label="t('value.color')" />
    </wx-form-item>

    <wx-form-item v-if="props.image" :label="t('value.image')" :error="errorOf('image_id')">
      <div class="wx-catalog-value-dialog__picture">
        <img v-if="picture" :src="picture.url" alt="" />
        <wx-button size="sm" variant="outline" icon="image" @click="pick">
          {{ picture ? t('panel.picture-change') : t('panel.picture-pick') }}
        </wx-button>
        <wx-button v-if="picture" size="sm" variant="text" @click="picture = null">
          {{ t('panel.picture-remove') }}
        </wx-button>
      </div>
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="!written()" @click="submit">
          {{ props.value ? t('panel.save') : t('panel.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-catalog-value-dialog__picture {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-value-dialog__picture img {
  width: var(--wx-space-48);
  height: var(--wx-space-48);
  border-radius: var(--wx-radius-sm);
  object-fit: cover;
  box-shadow: inset 0 0 0 1px var(--wx-border-default);
}
</style>
