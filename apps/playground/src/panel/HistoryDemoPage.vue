<script setup lang="ts">
import { ref } from 'vue'
import { provideHistorySubject, WxScreen, WxScreenHead } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'

/**
 * An editor of a record no module has: a kettle, with a `wx-history` tab on its form.
 *
 * What a module's editor does for the node is the one line below — say which record it is
 * showing. The screen itself is described (`server/panel/project/demo.product.json`) and the
 * rows come from the fake server's journal of this one record.
 */
const values = ref<ScreenModel>({
  name: 'Чайник электрический 1,7 л',
  sku: 'KT-1700',
  price: 2290,
  stock: 12,
  is_visible: true,
})

provideHistorySubject({ id: ref(7) })
</script>

<template>
  <div class="history-demo">
    <wx-screen-head title="Чайник электрический 1,7 л" subtitle="demo.product #7" />
    <wx-screen v-model="values" name="demo.product" />
  </div>
</template>

<style scoped>
.history-demo {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}
</style>
