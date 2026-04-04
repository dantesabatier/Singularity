import path from "node:path"
import {defineConfig} from "vite"

export default defineConfig(({command}) => ({
    base: command === "build" ? "/Build/" : "/",
    server: {
        host: "127.0.0.1",
        port: 5173,
        strictPort: true,
    },
    resolve: {
        alias: {
            "@": path.resolve(process.cwd(), "Frontend/ts"),
        },
    },
    build: {
        manifest: true,
        outDir: "Build",
        emptyOutDir: true,
        rollupOptions: {
            input: path.resolve(process.cwd(), "Frontend/ts/main.ts"),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: "modern-compiler",
            },
        },
    },
}))
