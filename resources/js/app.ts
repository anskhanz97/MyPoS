import { createApp } from "vue";
import App from "./App.vue";
import AppSelect from "./components/AppSelect.vue";
import AppDatePicker from "./components/AppDatePicker.vue";

createApp(App)
    .component("AppSelect", AppSelect)
    .component("AppDatePicker", AppDatePicker)
    .mount("#app");

if ("serviceWorker" in navigator && import.meta.env.PROD) {
    void navigator.serviceWorker.register("/sw.js");
}
