<template>
  <span class="brand">
    <span class="brand__mark" aria-hidden="true">W</span>
    <strong class="brand__name">Demo admin</strong>
  </span>
</template>

<style scoped>
.brand {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 0 0 auto;
}

.brand__mark {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  background: var(--wx-color-primary);
  color: var(--wx-text-inverse);
  border-radius: var(--wx-radius-sm);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-bold);
}

.brand__name {
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  white-space: nowrap;
}

/* Below this the mark carries the brand on its own. */
@media (max-width: 420px) {
  .brand__name {
    display: none;
  }
}
</style>
