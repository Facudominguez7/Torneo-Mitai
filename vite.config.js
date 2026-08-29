import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");

    return {
        build: {
            // Minificación con esbuild (incluido en Vite, más rápido que terser)
            minify: 'esbuild',
            // Code splitting para mejor caché
            rollupOptions: {
                output: {
                    manualChunks: {
                        vendor: ['alpinejs', 'axios'],
                    },
                },
            },
            // Advertir si chunks son muy grandes
            chunkSizeWarningLimit: 500,
        },
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
                // Si accedes desde otra PC, configurar VITE_HMR_HOST con la IP LAN del servidor
                host: env.VITE_HMR_HOST || "127.0.0.1",
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
    };
});
