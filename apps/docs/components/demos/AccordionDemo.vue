<script setup lang="ts">
import { ref } from 'vue'
import { WxAccordion, WxAccordionItem, WxBadge, WxSwitch } from '@webx-ui/core'

const open = ref('shipping')
const opened = ref<string[]>(['what'])
const comments = ref(true)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">One at a time · open: «{{ open || 'nothing' }}»</span>
      <wx-accordion v-model="open">
        <wx-accordion-item value="shipping" title="Shipping" subtitle="Nova Poshta, courier">
          Parcel 59000123456789, handed over on 13 March. The courier tries twice before the parcel
          goes back to the warehouse.
        </wx-accordion-item>
        <wx-accordion-item value="payment" title="Payment" subtitle="Card ending 4242">
          Paid in full on 12 March. A refund goes back to the same card.
        </wx-accordion-item>
        <wx-accordion-item value="notes" title="Notes">
          Nothing has been written about this order yet.
        </wx-accordion-item>
      </wx-accordion>
    </div>

    <div>
      <span class="wx-demo__label">A card per question, several open at once</span>
      <wx-accordion v-model="opened" multiple variant="separated" icon-position="start">
        <wx-accordion-item value="what" title="What is included in the price?">
          Hosting for the first year, the content migration and two rounds of edits.
        </wx-accordion-item>
        <wx-accordion-item value="long" title="How long does it take?">
          Four to six weeks, counting from the day the content is handed over.
          <template #extra><wx-badge type="info" size="sm">FAQ</wx-badge></template>
        </wx-accordion-item>
        <wx-accordion-item value="comments" title="Comments on the page">
          Turn the switch in the header — it works without opening the panel.
          <template #extra>
            <wx-switch v-model="comments" size="sm" aria-label="Allow comments" />
          </template>
        </wx-accordion-item>
      </wx-accordion>
    </div>

    <div>
      <span class="wx-demo__label">Compact, without a box around it</span>
      <wx-accordion variant="plain" size="sm">
        <wx-accordion-item value="a" title="Meta title" icon="search">
          Up to 60 characters, or Google cuts it off.
        </wx-accordion-item>
        <wx-accordion-item value="b" title="Meta description" icon="search">
          Up to 160 characters.
        </wx-accordion-item>
        <wx-accordion-item value="c" title="Canonical URL" icon="link" disabled>
          Set automatically from the slug.
        </wx-accordion-item>
      </wx-accordion>
    </div>
  </div>
</template>
