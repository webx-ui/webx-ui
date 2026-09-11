import { createApp } from 'vue'
import { WebxUI } from '@webx-ui/core'
/* The opt-in typeface: without this line the admin renders in the platform's own UI font. */
import '@webx-ui/tokens/fonts.css'
import App from './App.vue'

createApp(App).use(WebxUI).mount('#app')
