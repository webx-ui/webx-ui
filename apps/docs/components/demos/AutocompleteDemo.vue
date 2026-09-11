<script setup lang="ts">
import { ref } from 'vue'
import { WxAutocomplete, WxIcon, type AutocompleteOption } from '@webx-ui/core'

const city = ref('')
const cities: AutocompleteOption[] = [
  { value: 'Kyiv', description: 'Ukraine · 2.9M' },
  { value: 'Kharkiv', description: 'Ukraine · 1.4M' },
  { value: 'Odesa', description: 'Ukraine · 1.0M' },
  { value: 'Lviv', description: 'Ukraine · 0.7M' },
  { value: 'Krakow', description: 'Poland · 0.8M' },
]

/* The remote example: a fake backend, so the page needs no server. */
const author = ref('')
const found = ref<AutocompleteOption[]>([])
const loading = ref(false)
const lastQuery = ref('')

const people = [
  { name: 'Maria Kovalenko', role: 'Editor', id: 12 },
  { name: 'Alexey Sizintsev', role: 'Owner', id: 3 },
  { name: 'Petro Shevchenko', role: 'Author', id: 41 },
  { name: 'Olena Bondar', role: 'Author', id: 58 },
]

let request: ReturnType<typeof setTimeout> | undefined

function search(term: string) {
  lastQuery.value = term
  loading.value = true
  clearTimeout(request)

  request = setTimeout(() => {
    const needle = term.toLowerCase()
    found.value = people
      .filter((person) => person.name.toLowerCase().includes(needle))
      .map((person) => ({ value: person.name, description: person.role, id: person.id }))
    loading.value = false
  }, 600)
}

const picked = ref<AutocompleteOption | null>(null)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Suggestions from a local list</span>
      <wx-autocomplete v-model="city" :options="cities" placeholder="Start typing a city" clearable>
        <template #prefix><wx-icon name="search" /></template>
      </wx-autocomplete>
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 14px">
        Value: <code>{{ city || '—' }}</code> — free text, not one of the options
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Searched on the backend, with custom rows</span>
      <wx-autocomplete
        v-model="author"
        :options="found"
        :loading="loading"
        :min-length="2"
        remote
        placeholder="Find an author"
        clearable
        @search="search"
        @select="picked = $event"
      >
        <template #option="{ option }">
          <span style="display: flex; align-items: center; gap: 8px">
            <wx-icon name="user" />
            <span>{{ option.value }}</span>
            <span style="margin-left: auto; color: var(--wx-text-muted); font-size: 12px">
              #{{ option.id }} · {{ option.description }}
            </span>
          </span>
        </template>
      </wx-autocomplete>
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 14px">
        Last request: <code>{{ lastQuery || '—' }}</code> · picked:
        <code>{{ picked ? `${picked.value} (#${picked.id})` : '—' }}</code>
      </p>
    </div>
  </div>
</template>
