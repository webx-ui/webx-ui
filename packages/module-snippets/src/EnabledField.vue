<script setup lang="ts">
import { WxBadge, WxSwitch } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useSnippetsCopy } from './copy'
import { useSnippetsMessages } from './i18n'

/**
 * "Enabled" of the code form: the switch, and beside it the same grey mark as in the list when a
 * code switched on is still not printed on this copy of the site — it is not marked "on dev too".
 */
defineOptions({ name: 'WxSnippetsEnabled', inheritAttrs: false })

const model = defineModel<boolean | null>({ default: false })

useSnippetsMessages()
const t = useTranslate('webx-snippets')
const copy = useSnippetsCopy()
</script>

<template>
  <div class="wx-snippets-enabled">
    <wx-switch v-bind="$attrs" v-model="model" />
    <wx-badge
      v-if="model === true && copy.silent.value"
      class="wx-snippets-enabled__mark"
      size="sm"
      round
      :title="t('panel.not-here-help', { environment: copy.environment.value })"
    >
      {{ t('panel.not-here') }}
    </wx-badge>
  </div>
</template>

<style scoped>
.wx-snippets-enabled {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}
</style>
