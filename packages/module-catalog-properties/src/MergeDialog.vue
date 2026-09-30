<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxSelect,
  WxSpace,
  type SelectOption,
  type SelectModelValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createPropertiesApi } from './api'
import { wordsIn } from './format'
import { NAMESPACE } from './i18n'
import type { PropertyValue } from './types'

/**
 * «Merge with…»: one value into another of the same property (§3.3). The products of the first
 * get the second, its slugs become the second's old addresses, and the first is gone — so before
 * anything happens the dialog says how many products that is, counted by the server's dry run.
 *
 * The target is found by search, because a reference book of colours or materials is long, and a
 * merge is done when two spellings of one thing are noticed — by name.
 */
defineOptions({ name: 'WxCatalogPropertyMergeDialog' })

const props = defineProps<{ property: number; value: PropertyValue }>()

const { open, resolve, dismiss } = useModal<number>()

const admin = useAdmin()
const api = createPropertiesApi(admin)
const t = useTranslate(NAMESPACE)
const message = useErrorText()

const locale = computed(() => admin.i18n.state.locale)
const name = computed(() => wordsIn(props.value.title, locale.value, `#${props.value.id}`))

const found = ref<PropertyValue[]>([])
const target = ref<number | null>(null)
const moved = ref<number | null>(null)
const counting = ref(false)
const merging = ref(false)
const failure = ref('')

const options = computed<SelectOption[]>(() =>
  found.value
    .filter((one) => one.id !== props.value.id)
    .map((one) => ({ value: one.id, label: wordsIn(one.title, locale.value, `#${one.id}`) })),
)

const targetName = computed(
  () => options.value.find((option) => option.value === target.value)?.label ?? '',
)

let asked = 0

async function search(term: string): Promise<void> {
  const ticket = ++asked

  try {
    const answer = await api.searchValues(props.property, term.trim(), 30)

    if (ticket === asked) found.value = answer
  } catch {
    if (ticket === asked) found.value = []
  }
}

void search('')

watch(target, async (into) => {
  moved.value = null
  failure.value = ''

  if (into === null) return

  counting.value = true

  try {
    moved.value = await api.mergeValue(props.property, props.value.id, into, true)
  } catch (error) {
    failure.value = message(error)
  } finally {
    counting.value = false
  }
})

function pick(value: SelectModelValue): void {
  target.value = typeof value === 'number' ? value : null
}

async function merge(): Promise<void> {
  if (target.value === null || merging.value) return

  merging.value = true
  failure.value = ''

  try {
    resolve(await api.mergeValue(props.property, props.value.id, target.value))
  } catch (error) {
    failure.value = message(error)
  } finally {
    merging.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.merge-title', { name })" :width="480">
    <wx-form-item :label="t('panel.merge-into')" :help="t('panel.merge-help')">
      <wx-select
        :model-value="target"
        :options="options"
        :placeholder="t('panel.search')"
        :empty-text="t('panel.nothing-found')"
        filterable
        @update:model-value="pick"
        @search="search"
      />
    </wx-form-item>

    <wx-alert v-if="failure" type="danger" variant="soft" :description="failure" />
    <wx-alert
      v-else-if="target !== null && moved !== null"
      type="warning"
      variant="soft"
      :description="t('panel.merge-confirm', { name, target: targetName, count: moved })"
    />

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button
          type="primary"
          :loading="merging || counting"
          :disabled="target === null || moved === null"
          @click="merge"
        >
          {{ t('panel.merge') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
