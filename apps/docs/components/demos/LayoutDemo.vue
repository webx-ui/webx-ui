<script setup lang="ts">
import { ref } from 'vue'
import {
  WxAside,
  WxButton,
  WxContainer,
  WxFooter,
  WxHeader,
  WxMain,
  WxMenu,
  WxMenuItem,
} from '@webx-ui/core'

const collapsed = ref(false)
const section = ref('pages')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="layout-demo">
      <wx-container>
        <wx-header>
          <strong>Admin</strong>
          <wx-button size="sm" variant="outline" @click="collapsed = !collapsed">
            {{ collapsed ? 'Expand' : 'Collapse' }} the sidebar
          </wx-button>
        </wx-header>

        <wx-container direction="horizontal">
          <wx-aside :collapsed="collapsed" :width="200">
            <wx-menu v-model="section" size="sm" :collapsed="collapsed" label="Sections">
              <wx-menu-item value="pages" icon="file" label="Pages" />
              <wx-menu-item value="media" icon="image" label="Media" />
              <wx-menu-item value="users" icon="users" label="Users" />
            </wx-menu>
          </wx-aside>

          <wx-main>
            <p>
              The <code>{{ section }}</code> screen goes here.
            </p>
          </wx-main>
        </wx-container>

        <wx-footer>WebX UI — MIT</wx-footer>
      </wx-container>
    </div>
  </div>
</template>

<style scoped>
/* The shell is normally the page; here it is boxed so it fits in the docs. */
.layout-demo {
  height: 300px;
  overflow: hidden;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

.layout-demo :deep(.wx-header) {
  justify-content: space-between;
}
</style>
