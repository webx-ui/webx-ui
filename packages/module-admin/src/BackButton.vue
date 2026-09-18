<script setup lang="ts">
import { computed } from 'vue'
import { WxAction } from '@webx-ui/core'
import type { ActionSize } from '@webx-ui/core'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import { useTranslate } from './i18n'

/**
 * The way out of an editor, said plainly.
 *
 * A trail of breadcrumbs is where the reader *is*; it is not a way out, and on a phone it is
 * four words in the smallest type on the screen. Every screen that opens one record — a page,
 * a block, a rule — ends up needing the same control, so it is one control rather than each
 * editor's own arrow.
 *
 * An arrow and nothing else: the label is the tooltip, because the name of the section it
 * returns to is already written next to it in the trail, and twice is noise.
 */
const props = withDefaults(
  defineProps<{
    /** Where it leads — the section's list, normally. */
    to: RouteLocationRaw
    /** What the tooltip says. Defaults to the panel's own word for it. */
    label?: string
    size?: ActionSize
  }>(),
  { label: undefined, size: 'sm' },
)

const t = useTranslate('webx-admin')

const title = computed(() => props.label ?? t('editor.back'))
</script>

<template>
  <!--
    No styles of its own. The class is for the head that holds it to place it with; a rule
    written here would need the scope attribute, and what carries the class is another
    component's element, which does not have one.
  -->
  <wx-action
    icon="arrow-left"
    class="wx-back-button"
    :as="RouterLink"
    :to="to"
    :title="title"
    :size="size"
    tooltip-side="bottom"
  />
</template>
