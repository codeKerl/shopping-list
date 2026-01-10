import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './assets/main.css'
import { routes } from './router'
import { registerSW } from 'virtual:pwa-register'
import { initTheme } from './composables/useTheme'

initTheme()

const app = createApp(App)
const pinia = createPinia()
const router = createRouter({
  history: createWebHistory(),
  routes
})

app.use(pinia)
app.use(router)
app.mount('#app')

registerSW({ immediate: true })
