import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.ts"],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: "127.0.0.1",
        port: 5174,
        strictPort: true,
        cors: {
            origin: [
                "http://127.0.0.1:8000",
                "http://localhost:8000",
                "http://127.0.0.1:8173",
            ],
        },
        hmr: {
            host: "127.0.0.1",
            port: 5174,
        },
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
