<script setup lang="ts">
import { computed } from 'vue'
import { localeLabel, useLocales, type LocaleOption } from '../../composables/useLocalized'
import LocalePicker from './LocalePicker.vue'
import type { LocalesEmits, LocalesProps } from './types'

/**
 * Several fields that belong to one language, switched together.
 *
 * A single field does not need this — it takes `localized` and grows a picker of its own. This
 * is for a section: a heading, a lead and a body that are all the Ukrainian version of a page.
 */
defineOptions({ name: 'WxLocales' })

const props = withDefaults(defineProps<LocalesProps>(), {
  locales: undefined,
  variant: 'inline',
})

const emit = defineEmits<LocalesEmits>()

const context = useLocales()

const list = computed<LocaleOption[]>(() => props.locales ?? context.list.value)

/**
 * The language being edited is shared, so switching it here switches it everywhere on the
 * screen — a page half in one language and half in another is how a record gets saved
 * untranslated. A caller that wants this section on its own passes `v-model:locale`.
 */
const model = defineModel<string | undefined>('locale', { default: undefined })

const active = computed<string>(() => {
  const chosen = model.value ?? context.active.value
  const known = list.value.some((locale) => locale.code === chosen)

  return known ? chosen : (list.value[0]?.code ?? '')
})

function choose(code: string): void {
  if (code === active.value) return

  if (model.value === undefined) {
    context.active.value = code
  } else {
    model.value = code
  }

  emit('change', code)
}
</script>

<template>
  <div :class="['wx-locales', `wx-locales--${variant}`]">
    <div v-if="variant === 'tabs'" class="wx-locales__tabs" role="tablist">
      <button
        v-for="locale in list"
        :key="locale.code"
        type="button"
        role="tab"
        :aria-selected="locale.code === active"
        :class="['wx-locales__tab', { 'is-active': locale.code === active }]"
        @click="choose(locale.code)"
      >
        {{ localeLabel(locale) }}
      </button>
    </div>

    <!--
      Every language stays in the DOM, only one is shown: a control that is not rendered is a
      control a classic form post leaves out, and the other languages would be wiped on save.
    -->
    <!--
      With no languages configured the section is still a section: the slot is rendered once
      with none, rather than the whole thing disappearing because a setting is missing.
    -->
    <div v-if="list.length === 0" class="wx-locales__pane">
      <slot :locale="undefined" :active="true" />
    </div>

    <div
      v-for="locale in list"
      v-show="locale.code === active"
      :key="locale.code"
      class="wx-locales__pane"
    >
      <slot :locale="locale" :active="locale.code === active" />
    </div>

    <locale-picker
      v-if="variant === 'inline' && list.length > 1"
      :locales="list"
      :active="active"
      @choose="choose"
    />
  </div>
</template>

<style scoped>
.wx-locales--inline {
  position: relative;
}

.wx-locales__pane {
  min-width: 0;
}

.wx-locales__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
  margin-bottom: var(--wx-space-8);
}

.wx-locales__tab {
  height: var(--wx-size-control-sm);
  padding: 0 var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  background: var(--wx-bg-surface);
  color: var(--wx-text-muted);
  font-family: inherit;
  font-size: var(--wx-font-size-control-sm);
  font-weight: 600;
  text-transform: uppercase;
  cursor: pointer;
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-locales__tab:hover {
  color: var(--wx-text-default);
}

.wx-locales__tab.is-active {
  border-color: var(--wx-color-primary);
  background: var(--wx-color-primary);
  color: var(--wx-color-primary-contrast);
}
</style>
