const {FusesPlugin} = require("@electron-forge/plugin-fuses")
const {FuseV1Options, FuseVersion} = require("@electron/fuses")

// noinspection SpellCheckingInspection
module.exports = {
    packagerConfig: {
        name: "Singularity",
        appName: "Singularity",
        appBundleId: "com.sabatiersoftware.singularity",
        appCopyright: "Copyright © 2026 Dante Sabatier. All rights reserved.",
        icon: "icon",
        asar: true,
    },
    rebuildConfig: {},
    makers: [
        {
            name: "@electron-forge/maker-squirrel",
            config: {
                icon: "icon.ico",
                setupIcon: "icon.ico",
                language: 1033,
                manufacturer: "Dante Sabatier"
            }
        },
        {
            name: "@electron-forge/maker-deb",
            config: {
                options: {
                    icon: "icon.ico",
                    setupIcon: "icon.ico",
                    language: 1033,
                    manufacturer: "Dante Sabatier"
                }
            }
        }
    ],
    plugins: [
        {
            name: "@electron-forge/plugin-auto-unpack-natives",
            config: {},
        },
        {
            name: "@electron-forge/plugin-vite",
            config: {
                build: [
                    {
                        entry: "src/main.ts",
                        config: "vite.main.config.ts",
                        target: "main",
                    },
                    {
                        entry: "src/preload.ts",
                        config: "vite.preload.config.ts",
                        target: "preload",
                    },
                ],
                renderer: [
                    {
                        name: "main_window",
                        config: "vite.renderer.config.ts",
                    },
                ],
            },
        },
        new FusesPlugin({
            version: FuseVersion.V1,
            [FuseV1Options.RunAsNode]: false,
            [FuseV1Options.EnableCookieEncryption]: true,
            [FuseV1Options.EnableNodeOptionsEnvironmentVariable]: false,
            [FuseV1Options.EnableNodeCliInspectArguments]: false,
            [FuseV1Options.EnableEmbeddedAsarIntegrityValidation]: true,
            [FuseV1Options.OnlyLoadAppFromAsar]: true,
        }),
    ],
};
