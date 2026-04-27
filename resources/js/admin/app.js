import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import AdminApp from './AdminApp.vue'

createApp(AdminApp)
    .use(createPinia())
    .use(router)
    .mount('#admin-app')