<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxIcon, WxIndicator, WxLink } from '@webx-ui/core'

const cart = ref(2)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">On a button, an icon and a link</span>
      <div class="wx-demo__row" style="gap: 28px">
        <wx-indicator :value="cart" :label="`${cart} items in cart`">
          <wx-button type="warning" round>
            <template #icon><wx-icon name="cart" /></template>
            Cart
          </wx-button>
        </wx-indicator>

        <wx-indicator :value="cart" :offset="[-2, 2]">
          <wx-icon name="cart" :size="28" label="Cart" />
        </wx-indicator>

        <wx-indicator value="new" type="primary">
          <wx-link href="#indicator">Releases</wx-link>
        </wx-indicator>

        <wx-indicator dot type="danger">
          <wx-icon name="bell" :size="24" label="Notifications" />
        </wx-indicator>
      </div>
    </div>

    <div class="wx-demo__row">
      <wx-button size="sm" @click="cart += 1">Add one</wx-button>
      <wx-button size="sm" variant="text" @click="cart = 0">Empty the cart</wx-button>
      <span style="color: var(--wx-text-muted); font-size: 14px">
        A zero hides the mark unless <code>show-zero</code> is set.
      </span>
    </div>

    <div>
      <span class="wx-demo__label">Capped counts and standalone marks</span>
      <div class="wx-demo__row" style="gap: 28px">
        <wx-indicator :value="7">
          <wx-button variant="outline">Comments</wx-button>
        </wx-indicator>
        <wx-indicator :value="128">
          <wx-button variant="outline">Inbox</wx-button>
        </wx-indicator>
        <wx-indicator :value="128" :max="999">
          <wx-button variant="outline">Inbox</wx-button>
        </wx-indicator>

        <span class="wx-demo__row" style="gap: 8px">
          Orders <wx-indicator :value="12" type="neutral" /> <span>Failed</span>
          <wx-indicator :value="3" />
        </span>
      </div>
    </div>
  </div>
</template>
