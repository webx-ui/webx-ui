<script setup lang="ts">
import { WxAvatar, WxAvatarGroup } from '@webx-ui/core'

const people = ['Ada Lovelace', 'Grace Hopper', 'Alan Turing', 'Barbara Liskov', 'Donald Knuth']
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A name is enough</span>
      <div class="wx-demo__row">
        <wx-avatar v-for="name in people" :key="name" :name="name" :title="name" />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Sizes</span>
      <div class="wx-demo__row">
        <wx-avatar size="xs" name="Ada Lovelace" />
        <wx-avatar size="sm" name="Ada Lovelace" />
        <wx-avatar size="md" name="Ada Lovelace" />
        <wx-avatar size="lg" name="Ada Lovelace" />
        <wx-avatar size="xl" name="Ada Lovelace" />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">A picture, a glyph, a square</span>
      <div class="wx-demo__row">
        <wx-avatar src="https://i.pravatar.cc/80?img=15" name="Ada Lovelace" />
        <wx-avatar icon="users" />
        <wx-avatar shape="square" name="Nova Build" />
        <wx-avatar src="/broken.png" name="Grace Hopper" />
      </div>
      <span class="wx-demo__note">The last one points at nothing, and falls through to GH.</span>
    </div>

    <div>
      <span class="wx-demo__label">A group, and the ones past the limit</span>
      <div class="wx-demo__row">
        <wx-avatar-group :max="3">
          <wx-avatar v-for="name in people" :key="name" :name="name" />
        </wx-avatar-group>

        <wx-avatar-group size="sm">
          <wx-avatar v-for="name in people.slice(0, 3)" :key="name" :name="name" />
        </wx-avatar-group>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-demo__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-demo__note {
  display: block;
  margin-top: var(--wx-space-6);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>
