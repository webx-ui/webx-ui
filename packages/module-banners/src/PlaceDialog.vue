<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import {
  useLocales,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { createBannersApi } from './api'
import { useBannersMessages } from './i18n'
import type { PlaceRow } from './types'

/**
 * A place of somebody's own: a name, and a key when it is new (§5.4).
 *
 * The key does not follow the name the way a page's address follows its title: it is typed into
 * `banners('…')` by hand, and a key that quietly became `promo-2` would be a template asking for
 * a place that is not there. For the same reason a rename never touches it.
 *
 * The name is translated, and required in the default language — which is what the list falls
 * back on in a panel open in a language nobody named the place in.
 */
defineOptions({ name: 'WxBannerPlaceDialog' })

const props = withDefaults(defineProps<{ place?: PlaceRow | null }>(), { place: null })

const { open, resolve, dismiss } = useModal<PlaceRow>()

const api = createBannersApi(useAdmin())
const locales = useLocales()
useBannersMessages()

const t = useTranslate('webx-banners')

const editing = props.place !== null

/*
 * Every language when the server sent them (`titles`), the one the list shows otherwise — filed
 * under the default language, which is where a place with one name has it.
 */
function initial(): LocalizedValue {
  if (props.place === null) return {}
  if (props.place.titles) return { ...props.place.titles }

  const first = locales.list.value[0]?.code

  return first === undefined ? {} : { [first]: props.place.title }
}

const key = ref(props.place?.key ?? '')
const title = ref<LocalizedValue>(initial())
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

/** Something written in at least one language: the server says which one it needs. */
function written(): boolean {
  return Object.values(title.value).some((words) => words.trim() !== '')
}

/** The first line of a refusal under a field that may be named by language: `title.en`. */
function errorOf(name: string): string | undefined {
  const found = Object.keys(errors.value).find((one) => one === name || one.startsWith(`${name}.`))

  return found === undefined ? undefined : errors.value[found]?.[0]
}

async function submit(): Promise<void> {
  if (!written() || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(
      editing && props.place !== null
        ? await api.renamePlace(props.place.key, title.value)
        : await api.createPlace({ key: key.value.trim(), title: title.value }),
    )
  } catch (error) {
    // Refused under its own field: a key that is taken or spelled in a way no template could
    // write, a name missing in the default language.
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="editing ? t('places.rename-title') : t('places.new-title')"
    :width="440"
  >
    <wx-form-item :label="t('places.field-title')" :error="errorOf('title')" required>
      <div ref="field">
        <wx-input
          v-model="title"
          localized
          :aria-label="t('places.field-title')"
          @keyup.enter="submit"
        />
      </div>
    </wx-form-item>

    <wx-form-item
      :label="t('places.field-key')"
      :error="errorOf('key')"
      :help="editing ? undefined : t('places.key-help')"
      required
    >
      <wx-input
        v-model="key"
        :disabled="editing"
        :aria-label="t('places.field-key')"
        autocomplete="off"
        @keyup.enter="submit"
      />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('places.cancel') }}</wx-button>
        <wx-button
          type="primary"
          :loading="saving"
          :disabled="!written() || key.trim() === ''"
          @click="submit"
        >
          {{ editing ? t('places.save') : t('places.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
