<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxSelect } from '@webx-ui/core'
import { useAdmin } from '@webx-ui/module-admin'
import type { RenderContext } from '@webx-ui/schema'
import { createDeliveryApi } from '../api'
import { useDeliveryWords } from '../i18n'
import type { RegionRow } from '../types'

/**
 * `wx-delivery-region`: a region of the locality's country. It reads the country from the field
 * named by `countryField` on the same screen — so it is a `nested` type, the one way a field is
 * handed the rest of the model — and asks again when that changes.
 */
defineOptions({ name: 'WxDeliveryRegionField' })

const props = withDefaults(
  defineProps<{ disabled?: boolean; countryField?: string; context?: RenderContext }>(),
  { disabled: false, countryField: 'country', context: undefined },
)

const value = defineModel<number | null>({ default: null })

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()
const regions = ref<RegionRow[]>([])

const country = computed(() => {
  const code = props.context?.model[props.countryField]

  return typeof code === 'string' ? code : ''
})

watch(
  country,
  async (code, before) => {
    if (code === '') {
      regions.value = []

      return
    }

    try {
      regions.value = await api.regions(code)
    } catch {
      regions.value = []
    }

    // Another country: a region of the one before is not one of this.
    if (before !== undefined && before !== code) value.value = null
  },
  { immediate: true },
)

const options = computed(() => regions.value.map((one) => ({ value: one.id, label: one.name })))
</script>

<template>
  <wx-select
    v-model="value"
    :options="options"
    filterable
    clearable
    :placeholder="country === '' ? t('region.country-first') : t('locality.no-region')"
    :disabled="props.disabled || country === ''"
  />
</template>
