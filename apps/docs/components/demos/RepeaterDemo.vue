<script setup lang="ts">
import { ref } from 'vue'
import { WxFormItem, WxInput, WxRepeater, WxSelect, WxSwitch, WxTextarea } from '@webx-ui/core'

interface Office {
  city: string
  address: string
  kind: string
  main: boolean
}

const offices = ref<Office[]>([
  { city: 'Kyiv', address: 'Khreshchatyk 1', kind: 'office', main: true },
  { city: 'Lviv', address: 'Rynok 2', kind: 'warehouse', main: false },
])

const kinds = [
  { label: 'Office', value: 'office' },
  { label: 'Warehouse', value: 'warehouse' },
  { label: 'Pickup point', value: 'pickup' },
]

function newOffice(): Office {
  return { city: '', address: '', kind: 'office', main: false }
}

const questions = ref([
  { question: 'How long does delivery take?', answer: 'Two to four working days.' },
  { question: 'Do you ship abroad?', answer: 'Everywhere in the EU.' },
])
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A record per row, named by one of its own fields</span>

      <wx-repeater
        v-model="offices"
        :new-item="newOffice"
        item-label="city"
        add-label="Add an office"
        collapsible
        aria-label="Offices"
      >
        <template #default="{ item, update }">
          <wx-form-item label="City">
            <wx-input
              :model-value="item.city"
              placeholder="Kyiv"
              @update:model-value="update({ city: String($event ?? '') })"
            />
          </wx-form-item>

          <wx-form-item label="Address">
            <wx-input
              :model-value="item.address"
              @update:model-value="update({ address: String($event ?? '') })"
            />
          </wx-form-item>

          <wx-form-item label="Kind">
            <wx-select
              :model-value="item.kind"
              :options="kinds"
              @update:model-value="update({ kind: String($event ?? '') })"
            />
          </wx-form-item>

          <wx-form-item label="Main">
            <wx-switch
              :model-value="item.main"
              @update:model-value="update({ main: Boolean($event) })"
            />
          </wx-form-item>
        </template>

        <template #empty>No offices yet.</template>
      </wx-repeater>

      <span class="wx-demo__note">{{ offices.length }} offices, in this order.</span>
    </div>

    <div>
      <span class="wx-demo__label">Two fields and nothing else — no headers, no folding</span>

      <wx-repeater v-model="questions" add-label="Add a question" size="sm" aria-label="Questions">
        <template #default="{ item, update }">
          <wx-input
            :model-value="item.question"
            placeholder="The question"
            @update:model-value="update({ question: String($event ?? '') })"
          />
          <wx-textarea
            :model-value="item.answer"
            :rows="2"
            placeholder="The answer"
            @update:model-value="update({ answer: String($event ?? '') })"
          />
        </template>
      </wx-repeater>
    </div>
  </div>
</template>
