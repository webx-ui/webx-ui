<script setup lang="ts">
import { computed } from 'vue'
import type { ProseProps } from './types'

defineOptions({ name: 'WxProse' })

const props = withDefaults(defineProps<ProseProps>(), {
  html: undefined,
  size: 'md',
  as: 'div',
})

const classes = computed(() => ['wx-prose', `wx-prose--${props.size}`])
</script>

<template>
  <component :is="as" :class="classes">
    <!--
      The markup goes into a plain element rather than onto the dynamic tag itself:
      `v-html` on `<component :is>` is meaningless when `as` names a component, and
      every rule below matches descendants, so the extra div changes nothing visually.
    -->
    <div v-if="html !== undefined" class="wx-prose__content" v-html="html"></div>
    <slot v-else />
  </component>
</template>

<style scoped>
/*
 * Every rule is a `:deep` one on purpose: the markup usually arrives through `v-html`
 * and therefore carries no scope attribute of its own.
 */
.wx-prose {
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-prose-font-size, var(--wx-font-size-md));
  line-height: var(--wx-font-line-height-relaxed);
  /* The rhythm between blocks: one variable, so a caller can tighten the whole block. */
  --wx-prose-flow: var(--wx-space-16);
}

.wx-prose--sm {
  --wx-prose-font-size: var(--wx-font-size-sm);
  --wx-prose-flow: var(--wx-space-12);
}

.wx-prose--lg {
  --wx-prose-font-size: var(--wx-font-size-lg);
  --wx-prose-flow: var(--wx-space-18);
}

/* The rhythm: every block carries the gap below it, the last one drops it again. */
.wx-prose :deep(p),
.wx-prose :deep(ul),
.wx-prose :deep(ol),
.wx-prose :deep(dl),
.wx-prose :deep(blockquote),
.wx-prose :deep(pre),
.wx-prose :deep(figure),
.wx-prose :deep(table) {
  margin: 0 0 var(--wx-prose-flow);
}

.wx-prose :deep(:last-child) {
  margin-bottom: 0;
}

/* headings */
.wx-prose :deep(h1),
.wx-prose :deep(h2),
.wx-prose :deep(h3),
.wx-prose :deep(h4),
.wx-prose :deep(h5),
.wx-prose :deep(h6) {
  /* Air above a heading, tight to the paragraph it introduces. */
  margin: var(--wx-space-32) 0 var(--wx-space-12);
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
  text-wrap: balance;
}

.wx-prose :deep(h1) {
  font-size: var(--wx-font-size-3xl);
  font-weight: var(--wx-font-weight-bold);
}

.wx-prose :deep(h2) {
  font-size: var(--wx-font-size-2xl);
}

.wx-prose :deep(h3) {
  font-size: var(--wx-font-size-xl);
}

.wx-prose :deep(h4) {
  font-size: var(--wx-font-size-lg);
}

.wx-prose :deep(h5),
.wx-prose :deep(h6) {
  font-size: var(--wx-font-size-md);
}

.wx-prose :deep(:first-child) {
  margin-top: 0;
}

.wx-prose :deep(a) {
  color: var(--wx-text-link);
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.wx-prose :deep(a:hover) {
  color: var(--wx-color-primary-hover);
}

.wx-prose :deep(strong),
.wx-prose :deep(b) {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-prose :deep(small) {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-prose :deep(mark) {
  padding: 0 2px;
  background: var(--wx-color-warning-soft);
  border-radius: var(--wx-radius-none);
  color: inherit;
}

/* lists */
.wx-prose :deep(ul),
.wx-prose :deep(ol) {
  padding-left: var(--wx-space-24);
}

.wx-prose :deep(li + li) {
  margin-top: var(--wx-space-6);
}

.wx-prose :deep(li > ul),
.wx-prose :deep(li > ol) {
  margin-top: var(--wx-space-6);
}

.wx-prose :deep(ul) {
  list-style: disc;
}

.wx-prose :deep(ol) {
  list-style: decimal;
}

.wx-prose :deep(li::marker) {
  color: var(--wx-text-muted);
}

.wx-prose :deep(dt) {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-prose :deep(dd) {
  margin: 0 0 var(--wx-space-8);
  color: var(--wx-text-muted);
}

/* quotes and rules */
.wx-prose :deep(blockquote) {
  padding: var(--wx-space-4) var(--wx-space-16);
  border-left: 3px solid var(--wx-color-primary);
  color: var(--wx-text-muted);
}

.wx-prose :deep(hr) {
  height: 0;
  margin: var(--wx-space-24) 0;
  border: none;
  border-top: 1px solid var(--wx-border-default);
}

/* code */
.wx-prose :deep(code) {
  padding: 1px 5px;
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-xs);
  font-family: var(--wx-font-family-mono);
  font-size: 0.9em;
}

.wx-prose :deep(pre) {
  padding: var(--wx-space-14) var(--wx-space-16);
  background: var(--wx-bg-muted);
  border-radius: var(--wx-radius-sm);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
  overflow-x: auto;
}

.wx-prose :deep(pre code) {
  padding: 0;
  background: none;
  font-size: inherit;
}

/* media */
.wx-prose :deep(img),
.wx-prose :deep(video) {
  max-width: 100%;
  height: auto;
  border-radius: var(--wx-radius-sm);
}

.wx-prose :deep(figcaption) {
  margin-top: var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: center;
}

.wx-prose :deep(iframe) {
  max-width: 100%;
  border: none;
  border-radius: var(--wx-radius-sm);
}

/*
 * A pasted table is usually wider than the column it lands in, so it scrolls inside
 * its own box instead of pushing the page sideways.
 */
.wx-prose :deep(table) {
  display: block;
  width: 100%;
  max-width: 100%;
  border-collapse: collapse;
  font-size: var(--wx-font-size-sm);
  overflow-x: auto;
}

.wx-prose :deep(th),
.wx-prose :deep(td) {
  padding: var(--wx-space-8) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  text-align: start;
  vertical-align: top;
}

.wx-prose :deep(th) {
  background: var(--wx-bg-subtle);
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
  white-space: nowrap;
}

.wx-prose :deep(caption) {
  margin-bottom: var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: start;
}
</style>
