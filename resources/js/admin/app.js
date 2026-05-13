import { createApp } from "vue";
import { createPinia } from "pinia";
import router from "./router";
import AdminApp from "./AdminApp.vue";
import sprite from "./assets/icons/icons.svg?raw";

document.body.insertAdjacentHTML("afterbegin", sprite);

createApp(AdminApp).use(createPinia()).use(router).mount("#admin-app");
