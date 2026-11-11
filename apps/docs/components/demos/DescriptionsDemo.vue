<script setup lang="ts">
import { WxBadge, WxButton, WxDescriptions, WxDescriptionsItem } from '@webx-ui/core'
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Plain</span>
      <wx-descriptions title="Order WX-4100" :columns="2">
        <template #extra>
          <wx-button size="sm" variant="outline">Edit</wx-button>
        </template>

        <wx-descriptions-item label="Customer">Nova Build</wx-descriptions-item>
        <wx-descriptions-item label="Email">office@novabuild.example</wx-descriptions-item>
        <wx-descriptions-item label="Placed">11 September 2026</wx-descriptions-item>
        <wx-descriptions-item label="Status">
          <wx-badge type="success" size="sm">Shipped</wx-badge>
        </wx-descriptions-item>
        <wx-descriptions-item label="Note" :span="2">
          Leave at the gate; the yard is open until six.
        </wx-descriptions-item>
      </wx-descriptions>
    </div>

    <div>
      <span class="wx-demo__label">Bordered, three across</span>
      <wx-descriptions :columns="3" bordered size="sm">
        <wx-descriptions-item label="Invoice">WX-4100</wx-descriptions-item>
        <wx-descriptions-item label="Net">€3 400.00</wx-descriptions-item>
        <wx-descriptions-item label="VAT">€680.00</wx-descriptions-item>
        <wx-descriptions-item label="Terms" :span="2">30 days</wx-descriptions-item>
        <wx-descriptions-item label="Total">€4 080.00</wx-descriptions-item>
      </wx-descriptions>
    </div>

    <div>
      <span class="wx-demo__label">Labels above their values</span>
      <wx-descriptions layout="vertical" :columns="4" bordered>
        <wx-descriptions-item label="Items">3</wx-descriptions-item>
        <wx-descriptions-item label="Weight">12.4 kg</wx-descriptions-item>
        <wx-descriptions-item label="Carrier">Nova Post</wx-descriptions-item>
        <wx-descriptions-item label="Tracking">1Z 999 AA1</wx-descriptions-item>
      </wx-descriptions>
    </div>

    <div class="wx-demo__narrow">
      <span class="wx-demo__label">A long label in a narrow card</span>
      <wx-descriptions :columns="1" size="sm">
        <wx-descriptions-item label="Name">Ada Lovelace</wx-descriptions-item>
        <wx-descriptions-item
          label="Do not call back if everything is in stock — just send it to the address above"
        >
          Yes
        </wx-descriptions-item>
        <wx-descriptions-item label="Phone">+00 000 000 0000</wx-descriptions-item>
      </wx-descriptions>
    </div>
  </div>
</template>

<style scoped>
/* A card as narrow as a phone: where a long label used to take every pixel. */
.wx-demo__narrow {
  max-width: 360px;
}
</style>
