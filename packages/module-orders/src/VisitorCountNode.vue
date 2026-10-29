<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxText } from '@webx-ui/core'
import { useAdmin, useHistorySubject, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'

/**
 * `wx-orders-count` — «Orders: 12» in the side card `summary` of the visitor's card (spec
 * §13.2): the total of the list asked for one row, nothing until it is known.
 */
defineOptions({ name: 'WxOrdersCount' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const api = createOrdersApi(useAdmin())
const subject = useHistorySubject()

const total = ref<number | null>(null)

const id = computed(() => {
  const value = Number(subject?.id.value)

  return Number.isInteger(value) && value > 0 ? value : null
})

watch(
  id,
  async (user) => {
    total.value = null

    if (user === null) return

    try {
      total.value = (await api.list({ user, per_page: 1 })).total
    } catch {
      total.value = null
    }
  },
  { immediate: true },
)
</script>

<template>
  <wx-text v-if="total !== null" size="sm" class="wx-orders-count">
    {{ t('panel.visitor-orders-count', { count: total }) }}
  </wx-text>
</template>
