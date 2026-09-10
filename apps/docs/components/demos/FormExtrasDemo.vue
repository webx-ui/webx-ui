<script setup lang="ts">
import { ref } from 'vue'
import { WxColorPicker, WxDateRangePicker, WxRate, WxSlider, WxTagsInput } from '@webx-ui/core'

const rating = ref(3)
const halfRating = ref(3.5)
const volume = ref(40)
const price = ref([200, 700])
const period = ref<string[] | null>(['2026-03-02', '2026-04-08'])
const tags = ref(['news'])
const brand = ref('#427edd')

const allTags = ['news', 'guides', 'releases', 'cases', 'tutorials']
const suggestions = ref(allTags)

/** Stands in for a backend lookup. */
function searchTags(term: string) {
  suggestions.value = term
    ? allTags.filter((tag) => tag.toLowerCase().includes(term.toLowerCase()))
    : allTags
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Rate</span>
      <div class="wx-demo__stack">
        <wx-rate v-model="rating" show-value />
        <wx-rate v-model="halfRating" allow-half show-value />
        <wx-rate :model-value="4" readonly size="sm" />
      </div>
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ rating }}</code> · half: <code>{{ halfRating }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Slider</span>
      <wx-slider v-model="volume" show-value :marks="{ 0: '0', 50: '50', 100: '100' }" />
      <div style="margin-top: 20px">
        <wx-slider v-model="price" range :min="0" :max="1000" :step="50" show-value />
      </div>
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ volume }}</code> · range: <code>{{ JSON.stringify(price) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Tags — Enter adds, Backspace removes the last</span>
      <wx-tags-input
        v-model="tags"
        :suggestions="suggestions"
        placeholder="Add a tag"
        @search="searchTags"
      />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ JSON.stringify(tags) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Date range, two months at once</span>
      <wx-date-range-picker v-model="period" placeholder="Pick a period" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ JSON.stringify(period) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Colour</span>
      <wx-color-picker
        v-model="brand"
        clearable
        :presets="['#427edd', '#21c36d', '#ff9f43', '#f14646', '#21262c', '#ffffff']"
      />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ brand ?? 'null' }}</code>
      </p>
    </div>
  </div>
</template>
