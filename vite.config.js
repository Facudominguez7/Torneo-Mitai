import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/welcome.js",
                "resources/js/admin/admin.js",
            ],
            refresh: true,
        }),
    ],
    server: {
        host: true, // escucha en 0.0.0.0
        port: 5173,
        strictPort: true,
        hmr: {
            // En Docker usa 'localhost', en Laragon usa tu IP
            host: process.env.VITE_HMR_HOST || "127.0.0.1",
            port: 5173,
            protocol: "ws",
        },
        watch: {
            usePolling: false,
            ignored: ['**/node_modules/**', '**/vendor/**', '**/storage/**'],
            awaitWriteFinish: {
                stabilityThreshold: 100,
                pollInterval: 50
            }
        },
    },
});
