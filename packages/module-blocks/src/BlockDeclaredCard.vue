<script setup lang="ts">
import { useTranslate } from '@webx-ui/module-admin'
import { WxButton, WxIcon, WxText } from '@webx-ui/core'
import { useBlocksMessages } from './i18n'
import type { DeclaredComponent } from './types'

/**
 * A place a module declared and the site has not customised: the module draws its own view
 * there. It stands among the components rather than in a settings page, because otherwise nobody
 * learns the place can be changed at all (§3.9).
 *
 * No picture: there is no type to draw yet, and the module's view is drawn with data only the
 * module has. The card says whose view it is and offers the one thing to do with it.
 */
withDefaults(
  defineProps<{
    declared: DeclaredComponent
    /** The module's name as the panel shows it. */
    module: string
    canManage?: boolean
    busy?: boolean
  }>(),
  { canManage: false, busy: false },
)

const emit = defineEmits<{ customise: [declared: DeclaredComponent] }>()

useBlocksMessages()
const t = useTranslate('webx-blocks')
</script>

<template>
  <div class="wx-block-declared">
    <div class="wx-block-declared__mark" aria-hidden="true">
      <wx-icon name="sliders" />
    </div>
    <div class="wx-block-declared__body">
      <div class="wx-block-declared__title">{{ declared.title }}</div>
      <wx-text size="sm" tone="muted">
        <code>{{ declared.slug }}</code>
        · {{ t('components.standard', { module }) }}
      </wx-text>
      <wx-text v-if="declared.description" size="sm" tone="muted">
        {{ declared.description }}
      </wx-text>
      <wx-text size="sm" tone="muted" class="wx-block-declared__help">
        {{ t('components.standard-help') }}
      </wx-text>
      <div v-if="canManage" class="wx-block-declared__actions">
        <wx-button size="sm" :loading="busy" @click="emit('customise', declared)">
          {{ t('components.customise') }}
        </wx-button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* The same box as a type's card, with a dashed edge: a place, not yet a thing. */
.wx-block-declared {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  background: var(--wx-bg-surface);
  border: 1px dashed var(--wx-border-strong);
  border-radius: var(--wx-radius-md);
}

/* Where a type's card has its picture: the same height, so a row of cards stays level. */
.wx-block-declared__mark {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 120px;
  font-size: 32px;
  color: var(--wx-text-muted);
  background: var(--wx-bg-subtle);
  border-block-end: 1px dashed var(--wx-border-default);
}

.wx-block-declared__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-6);
  padding: var(--wx-space-12) var(--wx-space-14);
}

.wx-block-declared__title {
  font-weight: var(--wx-font-weight-semibold);
}

.wx-block-declared__actions {
  margin-block-start: auto;
  padding-block-start: var(--wx-space-4);
}

code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}
</style>
