<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxBadge } from '@webx-ui/core'
import { useAdmin } from '@webx-ui/module-admin'
import { createDeliveryApi } from '../api'
import { useDeliveryWords } from '../i18n'
import LocalitySearch from '../LocalitySearch.vue'
import type { LocalityBrief } from '../types'

/**
 * `wx-delivery-localities`: where the shop's courier goes (§10.2), kept as ids in the order
 * chosen. Each is a tag with its region; the search adds one, and a town the search does not know
 * is added right here (decision 42).
 */
defineOptions({ name: 'WxDeliveryLocalitiesField' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<number[] | null>({ default: null })

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()

const known = ref(new Map<number, LocalityBrief>())
const ids = computed(() => (Array.isArray(value.value) ? value.value : []))

watch(
  ids,
  async (list) => {
    const missing = list.filter((id) => !known.value.has(id))

    if (missing.length === 0) return

    try {
      const rows = await api.brief(missing)
      const next = new Map(known.value)

      for (const row of rows) next.set(row.id, row)
      known.value = next
    } catch {
      // The ids stay; their names come with the next answer.
    }
  },
  { immediate: true },
)

function add(locality: LocalityBrief): void {
  known.value = new Map(known.value).set(locality.id, locality)

  if (!ids.value.includes(locality.id)) value.value = [...ids.value, locality.id]
}

function remove(id: number): void {
  value.value = ids.value.filter((one) => one !== id)
}
</script>

<template>
  <div class="wx-delivery-localities-field">
    <div v-if="ids.length > 0" class="wx-delivery-localities-field__tags">
      <wx-badge
        v-for="id in ids"
        :key="id"
        :closable="!props.disabled"
        :type="known.get(id)?.active === false ? 'warning' : undefined"
        @close="remove(id)"
      >
        {{ known.get(id)?.label ?? `#${id}` }}
      </wx-badge>
    </div>

    <locality-search
      v-if="!props.disabled"
      creatable
      :exclude="ids"
      :placeholder="t('popular.add')"
      @pick="add"
    />
  </div>
</template>

<style scoped>
.wx-delivery-localities-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  width: 100%;
  min-width: 0;
}

.wx-delivery-localities-field__tags {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}
</style>
