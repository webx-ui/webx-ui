<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge } from '@webx-ui/core'
import { usePaymentsWords } from './i18n'
import type { MethodRow, PaymentMark } from './types'

/**
 * A method in the list (spec §13.2): its title, what it is, how it stands, its marks — read-only, a
 * type sets them (decision 2) — and whether the buyer could take it for another (groups spec §5).
 */
const props = defineProps<{
  method: MethodRow
  marks: Record<string, PaymentMark>
  current: boolean
}>()

defineEmits<{ choose: [] }>()

const t = usePaymentsWords()

/* The type and the gateway under the method's name, unless the name already says it. */
const line = computed(() => {
  const row = props.method
  const echo = row.type_title.trim().toLowerCase() === row.title.trim().toLowerCase()

  return [echo ? '' : row.type_title, row.gateway_title ?? '']
    .filter((part, index, all) => part !== '' && all.indexOf(part) === index)
    .join(' · ')
})

const similar = computed(() =>
  props.method.similar_to.length === 0
    ? null
    : t('methods.similar', { name: props.method.similar_to.map((one) => one.title).join(', ') }),
)

const shown = computed(() =>
  props.method.marks.flatMap((key) => {
    const mark = props.marks[key]

    return mark ? [{ key, ...mark }] : []
  }),
)
</script>

<template>
  <button
    type="button"
    class="wx-payments-method"
    :class="{ 'is-current': current }"
    @click="$emit('choose')"
  >
    <span class="wx-payments-method__title">{{ method.title }}</span>
    <span v-if="line" class="wx-payments-method__line">{{ line }}</span>
    <span v-if="shown.length > 0" class="wx-payments-method__marks">
      <template v-for="mark in shown" :key="mark.key">
        <img
          v-if="mark.url"
          class="wx-payments-method__mark"
          :src="mark.url"
          :width="mark.width"
          :height="mark.height"
          :alt="mark.alt"
        />
        <span v-else class="wx-payments-method__plate">{{ mark.alt }}</span>
      </template>
    </span>
    <span class="wx-payments-method__facts">
      <wx-badge v-if="!method.enabled" dot size="sm">{{ t('methods.off') }}</wx-badge>
      <wx-badge v-else type="success" dot size="sm">{{ t('methods.on') }}</wx-badge>
      <wx-badge v-if="method.mode" size="sm">{{
        method.mode === 'live' ? t('methods.live') : t('methods.test')
      }}</wx-badge>
      <wx-badge
        v-if="method.credentials"
        :type="
          method.credentials === 'set' || method.credentials === 'environment'
            ? 'default'
            : 'warning'
        "
        size="sm"
        >{{ t(`methods.keys.${method.credentials}`) }}</wx-badge
      >
      <wx-badge v-if="method.hold" size="sm">{{ t('methods.hold') }}</wx-badge>
      <wx-badge
        v-if="
          method.warnings.includes('type_missing') || method.warnings.includes('gateway_missing')
        "
        type="danger"
        size="sm"
        >{{ t(`methods.warnings.${method.warnings[0]}`) }}</wx-badge
      >
      <wx-badge v-if="similar" type="warning" size="sm" data-test="method-similar-badge">{{
        similar
      }}</wx-badge>
    </span>
  </button>
</template>

<style scoped>
.wx-payments-method {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  width: 100%;
  min-width: 0;
  padding-block: var(--wx-space-8);
  padding-inline: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-payments-method__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-payments-method.is-current .wx-payments-method__title {
  color: var(--wx-color-primary);
}

.wx-payments-method__line {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-payments-method__marks {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
}

.wx-payments-method__mark {
  display: block;
}

.wx-payments-method__plate {
  padding-inline: var(--wx-space-4);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-payments-method__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
}
</style>
