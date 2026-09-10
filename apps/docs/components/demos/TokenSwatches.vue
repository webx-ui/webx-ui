<script setup lang="ts">
import { computed } from 'vue'
import { tokens } from '@webx-ui/tokens'

const props = withDefaults(
  defineProps<{
    /** Which group to render: semantic colours, the palette, spacing, radii or font sizes. */
    group?: 'semantic' | 'palette' | 'space' | 'radius' | 'font-size'
    /** For `group="semantic"`: only keys starting with this prefix. */
    prefix?: string
    /** For `group="palette"`: which hue to show. */
    hue?: 'gray' | 'blue' | 'green' | 'amber' | 'red' | 'cyan'
  }>(),
  { group: 'semantic', prefix: 'color-', hue: 'blue' },
)

type Swatch = { name: string; value: string }

const semantic = computed<Swatch[]>(() =>
  Object.entries(tokens.semantic.light)
    .filter(([key]) => key.startsWith(props.prefix))
    .map(([key]) => ({ name: `--wx-${key}`, value: `var(--wx-${key})` })),
)

const palette = computed<Swatch[]>(() =>
  Object.entries(tokens.primitive.color[props.hue]).map(([step, value]) => ({
    name: `--wx-color-${props.hue}-${step}`,
    value: String(value),
  })),
)

const swatches = computed(() => (props.group === 'palette' ? palette.value : semantic.value))

const space = computed(() => Object.entries(tokens.primitive.space))
const radius = computed(() => Object.entries(tokens.primitive.radius))
const fontSize = computed(() => Object.entries(tokens.primitive.font.size))
</script>

<template>
  <div v-if="group === 'space'" class="wx-scale">
    <div v-for="[step, value] in space" :key="step" class="wx-scale__row">
      <span style="width: 130px">--wx-space-{{ step }}</span>
      <span class="wx-scale__bar" :style="{ width: value }" />
      <span>{{ value }}</span>
    </div>
  </div>

  <div v-else-if="group === 'radius'" class="wx-swatches">
    <div v-for="[name, value] in radius" :key="name" class="wx-swatch">
      <span
        class="wx-swatch__chip"
        :style="{ borderRadius: value, background: 'var(--wx-color-primary-soft)' }"
      />
      <span class="wx-swatch__meta">
        <span class="wx-swatch__name">--wx-radius-{{ name }}</span
        ><br />
        <span class="wx-swatch__value">{{ value }}</span>
      </span>
    </div>
  </div>

  <div v-else-if="group === 'font-size'" class="wx-scale">
    <div v-for="[name, value] in fontSize" :key="name" class="wx-scale__row">
      <span style="width: 130px">--wx-font-size-{{ name }}</span>
      <span :style="{ fontSize: value, color: 'var(--wx-text-default)' }">The quick brown fox</span>
      <span>{{ value }}</span>
    </div>
  </div>

  <div v-else class="wx-swatches">
    <div v-for="swatch in swatches" :key="swatch.name" class="wx-swatch">
      <span class="wx-swatch__chip" :style="{ background: swatch.value }" />
      <span class="wx-swatch__meta">
        <span class="wx-swatch__name">{{ swatch.name }}</span
        ><br />
        <span class="wx-swatch__value">{{ swatch.value }}</span>
      </span>
    </div>
  </div>
</template>
