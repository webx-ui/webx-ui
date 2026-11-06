<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxAction, WxDialog } from '@webx-ui/core'
import type { ActionSize } from '@webx-ui/core'
import { renderMarkdown } from './markdown'
import { useTranslate } from './i18n'

/**
 * A `?` beside something that needs explaining, and the page of explanation behind it.
 *
 * The text is Markdown and comes from wherever the caller keeps it — normally a `help` group
 * in a module's `lang` files, which means it is translated in the same place as everything
 * else and is already on the client with the rest of the dictionary. No request, no second
 * store, and a language nobody has translated falls back to the site's own the way every
 * other line does.
 *
 * Keeping it as Markdown rather than as a screen of components is what lets the same words
 * serve an agent: a module can hand the identical text to MCP as `text/markdown`, and then
 * there is one explanation of a thing rather than two that drift.
 */
const props = withDefaults(
  defineProps<{
    /** The page, in Markdown. */
    body: string
    /** Heading of the dialog. Defaults to the panel's own word for it. */
    title?: string
    /** Tooltip of the button. Defaults to the heading. */
    label?: string
    size?: ActionSize
    width?: number | string
  }>(),
  { title: undefined, label: undefined, size: 'sm', width: 720 },
)

const t = useTranslate('webx-admin')

const open = ref(false)

const heading = computed(() => props.title ?? t('help.title'))
const html = computed(() => renderMarkdown(props.body))
</script>

<template>
  <wx-action
    type="details"
    icon="question"
    tone="neutral"
    :size="size"
    :title="label ?? heading"
    @click="open = true"
  />

  <wx-dialog v-model:open="open" :title="heading" :width="width">
    <!--
      `v-html`, and the renderer is why it is safe: it escapes every character of the source
      before it emits a tag, so what lands here contains only the tags it built.
    -->
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div class="wx-help" v-html="html" />
  </wx-dialog>
</template>

<style src="./helpProse.css"></style>
