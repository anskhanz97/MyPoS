import { createApp } from "vue";
import App from "./App.vue";

createApp(App).mount("#app");

if ("serviceWorker" in navigator && import.meta.env.PROD) {
    void navigator.serviceWorker.register("/sw.js");
}
