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
            output: {
                manualChunks(id: string): string | undefined {
                    if (!id.includes("node_modules")) {
                        return undefined
                    }
                    if (id.includes("cytoscape") || id.includes("dagre")) {
                        return "vendor-graph"
                    }
                    if (id.includes("db-viewer-component")) {
                        return "vendor-viewer"
                    }
                    if (id.includes("bootstrap") || id.includes("@popperjs")) {
                        return "vendor-ui"
                    }
                    return "vendor"
                },
            },
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
