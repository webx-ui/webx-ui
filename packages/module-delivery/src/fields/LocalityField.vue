<script setup lang="ts">
import { ref, watch } from 'vue'
import { WxBadge, WxButton } from '@webx-ui/core'
import { useAdmin } from '@webx-ui/module-admin'
import { createDeliveryApi } from '../api'
import { useDeliveryWords } from '../i18n'
import LocalitySearch from '../LocalitySearch.vue'
import type { LocalityBrief } from '../types'

/**
 * `wx-delivery-locality`: the locality of a point (§14.2), kept by id. The chosen one is a line
 * with its region; «Change» opens the search, and a town the search does not know is added right
 * here (decision 42).
 */
defineOptions({ name: 'WxDeliveryLocalityField' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<number | null>({ default: null })

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()

const chosen = ref<LocalityBrief | null>(null)
const changing = ref(false)

watch(
  value,
  async (id) => {
    if (id === null || id === undefined) {
      chosen.value = null

      return
    }

    if (chosen.value?.id === id) return

    try {
      chosen.value = (await api.brief([id]))[0] ?? null
    } catch {
      chosen.value = null
    }
  },
  { immediate: true },
)

function pick(locality: LocalityBrief): void {
  chosen.value = locality
  value.value = locality.id
  changing.value = false
}
</script>

<template>
  <div class="wx-delivery-locality-field">
    <div v-if="chosen && !changing" class="wx-delivery-locality-field__chosen">
      <span class="wx-delivery-locality-field__label">{{ chosen.label }}</span>
      <wx-badge v-if="!chosen.active" size="sm" type="warning">{{
        t('locality.inactive')
      }}</wx-badge>
      <wx-button
        v-if="!props.disabled"
        size="sm"
        variant="text"
        icon="edit"
        @click="changing = true"
      >
        {{ t('common.search') }}
      </wx-button>
    </div>

    <locality-search v-else creatable :disabled="props.disabled" @pick="pick" />
  </div>
</template>

<style scoped>
.wx-delivery-locality-field {
  width: 100%;
  min-width: 0;
}

.wx-delivery-locality-field__chosen {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-height: var(--wx-control-height-md, 32px);
}

.wx-delivery-locality-field__label {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
