<script setup lang="ts">
import { computed, ref } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { IconName } from '../Icon/types'
import type { ThemePreference, ThemeSwitchEmits, ThemeSwitchProps } from './types'

defineOptions({ name: 'WxThemeSwitch' })

/**
 * Light, whatever the machine says, dark.
 *
 * It only reports the choice: applying it is `applyTheme()`'s job, and remembering it belongs
 * to whoever knows where this person's settings live. Three states rather than two because
 * "follow the machine" is a real answer and cannot be spelled with a toggle — a two-way
 * switch can only ever guess which side of it means "I have not decided".
 */
const props = withDefaults(defineProps<ThemeSwitchProps>(), {
  size: 'md',
  block: false,
  disabled: false,
  ariaLabel: 'Theme',
  lightLabel: 'Light',
  darkLabel: 'Dark',
  systemLabel: 'Follow the system',
})

const emit = defineEmits<ThemeSwitchEmits>()

const model = defineModel<ThemePreference>({ default: 'system' })

interface Choice {
  value: ThemePreference
  icon: IconName
  label: string
}

// Day, the room's own answer, night — the order somebody would draw it in.
const choices = computed<Choice[]>(() => [
  { value: 'light', icon: 'sun', label: props.lightLabel },
  { value: 'system', icon: 'monitor', label: props.systemLabel },
  { value: 'dark', icon: 'moon', label: props.darkLabel },
])

const index = computed(() => {
  const found = choices.value.findIndex((choice) => choice.value === model.value)

  return found === -1 ? 1 : found
})

const buttons = ref<HTMLButtonElement[]>([])

function choose(value: ThemePreference): void {
  if (props.disabled || value === model.value) {
    return
  }

  model.value = value
  emit('change', value)
}

/**
 * Arrow keys move the choice. A native radio group does this for free; a row of buttons
 * wearing `role="radio"` has to be told, and without it the keyboard can reach the control
 * and then do nothing with it.
 */
function onKeydown(event: KeyboardEvent): void {
  const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key]

  if (step === undefined) {
    return
  }

  event.preventDefault()

  const next = (index.value + step + choices.value.length) % choices.value.length

  choose(choices.value[next]!.value)
  buttons.value[next]?.focus()
}
</script>

<template>
  <div
    class="wx-theme-switch"
    :class="[
      `wx-theme-switch--${size}`,
      { 'wx-theme-switch--block': block, 'is-disabled': disabled },
    ]"
    :style="{ '--wx-theme-switch-index': index }"
    :data-chosen="model"
    role="radiogroup"
    :aria-label="ariaLabel"
    @keydown="onKeydown"
  >
    <!-- The lit cell, which slides rather than jumps: the picture of a switch being thrown. -->
    <span class="wx-theme-switch__thumb" aria-hidden="true" />

    <button
      v-for="(choice, position) in choices"
      :key="choice.value"
      :ref="
        (element) => {
          if (element) buttons[position] = element as HTMLButtonElement
        }
      "
      type="button"
      role="radio"
      class="wx-theme-switch__option"
      :class="{ 'is-chosen': choice.value === model }"
      :aria-checked="choice.value === model"
      :aria-label="choice.label"
      :title="choice.label"
      :disabled="disabled"
      :tabindex="choice.value === model ? 0 : -1"
      @click="choose(choice.value)"
    >
      <wx-icon
        :name="choice.icon"
        class="wx-theme-switch__icon"
        :class="`wx-theme-switch__icon--${choice.value}`"
      />
    </button>
  </div>
</template>

<style scoped>
.wx-theme-switch {
  position: relative;
  display: inline-flex;
  box-sizing: border-box;
  padding: 2px;
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-full);
  font-family: var(--wx-font-family-sans);
}

.wx-theme-switch--block {
  display: flex;
  width: 100%;
}

.wx-theme-switch--sm {
  --wx-theme-switch-cell: calc(var(--wx-size-control-sm) - 4px);
}

.wx-theme-switch--md {
  --wx-theme-switch-cell: calc(var(--wx-size-control-md) - 4px);
}

.wx-theme-switch--lg {
  --wx-theme-switch-cell: calc(var(--wx-size-control-lg) - 4px);
}

.wx-theme-switch__thumb {
  position: absolute;
  inset-block: 2px;
  inset-inline-start: 2px;
  /* Three equal cells inside the track, whatever width the track was given. */
  width: calc((100% - 4px) / 3);
  border-radius: var(--wx-radius-full);
  /*
   * The tint over the surface rather than instead of it: in the dark theme the soft colours
   * are translucent, and laid straight on the track they would leave the thumb the same
   * colour as the groove it is supposed to be lifted out of.
   */
  background:
    linear-gradient(
      var(--wx-theme-switch-tint, transparent),
      var(--wx-theme-switch-tint, transparent)
    ),
    var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-card);
  transform: translateX(calc(var(--wx-theme-switch-index, 1) * 100%));
  transition:
    transform var(--wx-duration-slow) var(--wx-easing-emphasized),
    background var(--wx-duration-normal) var(--wx-easing-standard);
}

/* Right to left the cells are drawn the other way round, and so is the slide. */
[dir='rtl'] .wx-theme-switch__thumb {
  transform: translateX(calc(var(--wx-theme-switch-index, 1) * -100%));
}

.wx-theme-switch[data-chosen='light'] {
  --wx-theme-switch-tint: var(--wx-color-warning-soft);
}

.wx-theme-switch[data-chosen='dark'] {
  --wx-theme-switch-tint: var(--wx-color-primary-soft);
}

.wx-theme-switch__option {
  position: relative;
  display: inline-flex;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-width: var(--wx-theme-switch-cell);
  height: var(--wx-theme-switch-cell);
  padding: 0;
  border: none;
  border-radius: var(--wx-radius-full);
  background: transparent;
  color: var(--wx-text-muted);
  cursor: pointer;
  transition: color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-theme-switch__option:hover:not(:disabled):not(.is-chosen) {
  color: var(--wx-text-default);
}

.wx-theme-switch__option:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-theme-switch__option:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-theme-switch__icon {
  /* Drawn over the thumb, which is the element before it in the stack. */
  position: relative;
  flex: 0 0 auto;
}

/*
 * The chosen picture arrives rather than appears: the sun rises into place, the moon swings
 * in, the screen is set down. It runs on the class being added, so it plays again every time
 * the choice comes back round.
 */
.is-chosen .wx-theme-switch__icon--light {
  color: var(--wx-color-warning);
  animation: wx-theme-switch-sun var(--wx-duration-slow) var(--wx-easing-emphasized);
}

.is-chosen .wx-theme-switch__icon--dark {
  color: var(--wx-color-primary);
  animation: wx-theme-switch-moon var(--wx-duration-slow) var(--wx-easing-emphasized);
}

.is-chosen .wx-theme-switch__icon--system {
  color: var(--wx-text-strong);
  animation: wx-theme-switch-monitor var(--wx-duration-slow) var(--wx-easing-emphasized);
}

@keyframes wx-theme-switch-sun {
  from {
    transform: rotate(-120deg) scale(0.4);
  }

  to {
    transform: none;
  }
}

@keyframes wx-theme-switch-moon {
  from {
    transform: rotate(45deg) scale(0.4);
  }

  to {
    transform: none;
  }
}

@keyframes wx-theme-switch-monitor {
  from {
    transform: translateY(4px) scale(0.7);
  }

  to {
    transform: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-theme-switch__thumb,
  .wx-theme-switch__option {
    transition: none;
  }

  .is-chosen .wx-theme-switch__icon {
    animation: none;
  }
}
</style>
