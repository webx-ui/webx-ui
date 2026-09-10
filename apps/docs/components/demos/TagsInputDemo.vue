<script setup lang="ts">
import { ref } from 'vue'
import { WxInput, WxTagsInput } from '@webx-ui/core'

const tags = ref(['news'])
const limited = ref<string[]>([])

const all = ['news', 'guides', 'releases', 'cases', 'tutorials']
const suggestions = ref(all)

/** Stands in for a backend lookup. */
function search(term: string) {
  suggestions.value = term ? all.filter((tag) => tag.includes(term.toLowerCase())) : all
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">With suggestions</span>
      <wx-tags-input
        v-model="tags"
        :suggestions="suggestions"
        placeholder="Add a tag"
        @search="search"
      />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ JSON.stringify(tags) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Beside a plain input — the two line up</span>
      <div class="wx-demo__stack">
        <wx-input model-value="A plain input" />
        <wx-tags-input v-model="tags" placeholder="Tags" />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">At most three, no free tags</span>
      <wx-tags-input
        v-model="limited"
        :suggestions="all"
        :max="3"
        :allow-create="false"
        placeholder="Pick up to three"
      />
    </div>
  </div>
</template>
