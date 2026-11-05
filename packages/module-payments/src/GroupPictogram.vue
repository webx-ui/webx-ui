<script setup lang="ts">
/**
 * A pictogram of the package's set (groups spec §2.5). The drawing comes from the server's view, a
 * theme may redraw it; the frame here is the one `<x-webx-payments::pictogram>` prints on the site.
 */
defineProps<{ svg: string }>()
</script>

<!-- eslint-disable vue/no-v-html -- the package's and the theme's own files, no input of anyone's -->
<template>
  <svg
    class="wx-payments-pictogram"
    viewBox="0 0 24 24"
    width="24"
    height="24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    v-html="svg"
  />
</template>

<style scoped>
.wx-payments-pictogram {
  flex: none;
}
</style>
