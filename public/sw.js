const SHELL_CACHE = "mypos-shell-v1";
const SHELL = ["/", "/manifest.webmanifest"];

self.addEventListener("install", (event) => event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL))));
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));
self.addEventListener("fetch", (event) => {
    const request = event.request;
    if (request.method !== "GET" || request.url.includes("/api/")) return;
    if (request.mode === "navigate") {
        event.respondWith(fetch(request).then((response) => {
            const copy = response.clone();
            void caches.open(SHELL_CACHE).then((cache) => cache.put("/", copy));
            return response;
        }).catch(() => caches.match("/").then((cached) => cached ?? Response.error())));
        return;
    }
    event.respondWith(caches.match(request).then((cached) => cached ?? fetch(request).then((response) => {
        if (new URL(request.url).origin === self.location.origin && /\.(?:js|css|woff2?)$/.test(new URL(request.url).pathname)) {
            const copy = response.clone();
            void caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
        }
        return response;
    })));
});
