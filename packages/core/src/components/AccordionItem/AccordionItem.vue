<script setup lang="ts">
import { computed } from 'vue'
import { AccordionContent, AccordionHeader, AccordionItem, AccordionTrigger } from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import { useAccordion } from '../../composables/useAccordion'
import type { AccordionItemProps } from './types'

defineOptions({ name: 'WxAccordionItem' })

const props = withDefaults(defineProps<AccordionItemProps>(), {
  title: undefined,
  subtitle: undefined,
  icon: undefined,
  disabled: false,
})

defineSlots<{
  /** The panel. */
  default?: () => unknown
  /** Replaces `title`. */
  title?: () => unknown
  /**
   * Sits at the end of the header, outside the button — so a switch or a menu there
   * can be operated without opening the item.
   */
  extra?: () => unknown
}>()

const accordion = useAccordion()

const size = computed(() => accordion?.size.value ?? 'md')
const iconPosition = computed(() => accordion?.iconPosition.value ?? 'end')
const headingTag = computed(() => accordion?.headingTag.value ?? 'h3')

const classes = computed(() => [
  'wx-accordion-item',
  `wx-accordion-item--${accordion?.variant.value ?? 'bordered'}`,
  `wx-accordion-item--${size.value}`,
  `wx-accordion-item--chevron-${iconPosition.value}`,
  // Everything standing before the title moves the panel text under it as well.
  { 'wx-accordion-item--icon': Boolean(props.icon) },
])
</script>

<template>
  <accordion-item :class="classes" :value="value" :disabled="disabled">
    <!--
      The heading holds the button and nothing else, which is what a screen reader
      reads out when it walks the headings of a page. Whatever the `extra` slot carries
      — a switch, a menu — sits beside the heading rather than inside it.
    -->
    <div class="wx-accordion-item__row">
      <accordion-header class="wx-accordion-item__header" :as="headingTag">
        <accordion-trigger class="wx-accordion-item__trigger">
          <wx-icon class="wx-accordion-item__chevron" name="chevron-down" />

          <wx-icon v-if="icon" class="wx-accordion-item__icon" :name="icon" />

          <span class="wx-accordion-item__titles">
            <span class="wx-accordion-item__title">
              <slot name="title">{{ title }}</slot>
            </span>
            <span v-if="subtitle" class="wx-accordion-item__subtitle">{{ subtitle }}</span>
          </span>
        </accordion-trigger>
      </accordion-header>

      <span v-if="$slots.extra" class="wx-accordion-item__extra">
        <slot name="extra" />
      </span>
    </div>

    <accordion-content class="wx-accordion-item__content">
      <div class="wx-accordion-item__body">
        <slot />
      </div>
    </accordion-content>
  </accordion-item>
</template>

<style scoped>
.wx-accordion-item {
  --wx-accordion-padding-x: var(--wx-space-16);
  --wx-accordion-padding-y: var(--wx-space-14);
  /* Air between the header and what it opened — see the body's padding. */
  --wx-accordion-gap: var(--wx-space-8);
  /* One mark — the chevron, or the icon — plus the gap after it. */
  --wx-accordion-mark: calc(var(--wx-font-size-lg) + var(--wx-space-10));
  --wx-accordion-chevron-indent: 0px;
  --wx-accordion-icon-indent: 0px;

  min-width: 0;
}

/* The panel text lines up with the title, not with whatever stands to its left. */
.wx-accordion-item--chevron-start {
  --wx-accordion-chevron-indent: var(--wx-accordion-mark);
}

.wx-accordion-item--icon {
  --wx-accordion-icon-indent: var(--wx-accordion-mark);
}

.wx-accordion-item--sm {
  --wx-accordion-padding-x: var(--wx-space-12);
  --wx-accordion-padding-y: var(--wx-space-8);
  --wx-accordion-gap: var(--wx-space-4);
}

/* Inside one box the items are told apart by a rule, not by a border each. */
.wx-accordion-item--bordered + .wx-accordion-item--bordered,
.wx-accordion-item--plain + .wx-accordion-item--plain {
  border-top: 1px solid var(--wx-border-muted);
}

.wx-accordion-item--separated {
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  overflow: hidden;
}

.wx-accordion-item__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  transition: background var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-accordion-item__header {
  flex: 1 1 auto;
  min-width: 0;
  margin: 0;
  font-size: inherit;
  font-weight: inherit;
}

.wx-accordion-item__trigger {
  display: flex;
  align-items: center;
  flex: 1 1 auto;
  gap: var(--wx-space-10);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  padding: var(--wx-accordion-padding-y) var(--wx-accordion-padding-x);
  background: none;
  border: none;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

/*
 * The tint belongs to the row, not to the button: the `extra` slot sits beside the
 * button, and a highlight that stopped at its edge left a seam down the header. It is
 * still the button that is being hovered — pointing at a switch in the `extra` must not
 * suggest that the section is about to open.
 */
.wx-accordion-item__row:has(.wx-accordion-item__trigger:hover:not(:disabled)) {
  background: var(--wx-bg-subtle);
}

.wx-accordion-item__trigger:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-accordion-item__trigger:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

/* The chevron is written first and moved by order, so the button's text order holds. */
.wx-accordion-item--chevron-end .wx-accordion-item__chevron {
  order: 2;
  margin-left: auto;
}

.wx-accordion-item__chevron {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-lg);
  transition: transform var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-accordion-item__trigger[data-state='open'] .wx-accordion-item__chevron {
  transform: rotate(180deg);
}

.wx-accordion-item__icon {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-lg);
}

.wx-accordion-item__titles {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.wx-accordion-item__title {
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-tight);
}

.wx-accordion-item--sm .wx-accordion-item__title {
  font-size: var(--wx-font-size-sm);
}

.wx-accordion-item__subtitle {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-regular);
  line-height: var(--wx-font-line-height-normal);
}

.wx-accordion-item__extra {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
  gap: var(--wx-space-6);
  padding-right: var(--wx-accordion-padding-x);
}

/*
 * Reka measures the panel and publishes the height as a custom property, which is what
 * makes an animation from nothing to "however tall this turns out to be" possible.
 * The padding lives on the body inside, so the height being animated is only content.
 */
.wx-accordion-item__content {
  overflow: hidden;
}

.wx-accordion-item__content[data-state='open'] {
  animation: wx-accordion-open var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-accordion-item__content[data-state='closed'] {
  animation: wx-accordion-close var(--wx-duration-normal) var(--wx-easing-standard);
}

/*
 * The header's own padding ends at the edge of the row it highlights, so without this
 * the panel starts flush against that edge and the two read as one block. The gap is
 * the panel's, and it is measured into the height that is animated.
 */
.wx-accordion-item__body {
  padding-top: var(--wx-accordion-gap);
  padding-right: var(--wx-accordion-padding-x);
  padding-bottom: var(--wx-accordion-padding-y);
  padding-left: calc(
    var(--wx-accordion-padding-x) + var(--wx-accordion-chevron-indent) +
      var(--wx-accordion-icon-indent)
  );
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

@keyframes wx-accordion-open {
  from {
    height: 0;
  }

  to {
    height: var(--reka-accordion-content-height);
  }
}

@keyframes wx-accordion-close {
  from {
    height: var(--reka-accordion-content-height);
  }

  to {
    height: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-accordion-item__content[data-state='open'],
  .wx-accordion-item__content[data-state='closed'] {
    animation: none;
  }

  .wx-accordion-item__chevron,
  .wx-accordion-item__row {
    transition: none;
  }
}

/* A header is a tap target; on a touch screen it needs the height of one. */
@media (pointer: coarse) {
  .wx-accordion-item__trigger {
    min-height: 48px;
  }
}
</style>
