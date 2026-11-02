<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { WxSelect } from '@webx-ui/core'
import { useAdmin } from '@webx-ui/module-admin'
import { createDeliveryApi } from '../api'
import type { CountryRow } from '../types'

/**
 * `wx-delivery-country`: a country of the site by its code. The site's countries, the ones on
 * first; a locality is made in a country the shop already knows (Geography → Countries).
 */
defineOptions({ name: 'WxDeliveryCountryField' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<string | null>({ default: null })

const api = createDeliveryApi(useAdmin())
const countries = ref<CountryRow[]>([])

onMounted(async () => {
  try {
    countries.value = (await api.countries()).countries
  } catch {
    countries.value = []
  }

  // One country on: it is the answer, and the field fills itself.
  const on = countries.value.filter((one) => one.active)

  if (value.value === null && on.length === 1) value.value = on[0]!.code
})

const options = computed(() =>
  countries.value.map((one) => ({ value: one.code, label: `${one.name} (${one.code})` })),
)
</script>

<template>
  <wx-select v-model="value" :options="options" filterable :disabled="props.disabled" />
</template>
