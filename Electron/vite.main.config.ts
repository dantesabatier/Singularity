// noinspection JSUnusedGlobalSymbols

import type {Plugin} from "vite"
import {defineConfig} from "vite"
import fs from "node:fs"
import path from "node:path"
import {fileURLToPath} from "node:url"

const __dirname = path.dirname(fileURLToPath(import.meta.url))

/** Forge only packages `/.vite`; copy runtime assets next to `main.js`. */
function copyMainAssets(): Plugin {
    return {
        name: "copy-main-assets",
        closeBundle() {
            const outDir = path.join(__dirname, ".vite/build")
            const icon = path.join(__dirname, "icon.png")
            if (fs.existsSync(icon)) {
                fs.copyFileSync(icon, path.join(outDir, "icon.png"))
            }
        },
    }
}

// https://vitejs.dev/config
export default defineConfig({
    plugins: [copyMainAssets()],
})
