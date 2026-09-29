import { defineConfig } from "@playwright/test";

export default defineConfig({
    testDir: "tests/browser",
    use: { baseURL: "http://127.0.0.1:8173", trace: "retain-on-failure" },
    webServer: {
        command: "php artisan serve --host=127.0.0.1 --port=8173",
        url: "http://127.0.0.1:8173/up",
        reuseExistingServer: false,
    },
});
