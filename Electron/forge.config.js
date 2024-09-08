const {FusesPlugin} = require("@electron-forge/plugin-fuses")
const {FuseV1Options, FuseVersion} = require("@electron/fuses")

// noinspection SpellCheckingInspection
module.exports = {
    packagerConfig: {
        name: "Singularity",
        appName: "Singularity",
        appBundleId: "com.sabatiersoftware.singularity",
        appCopyright: "Copyright © 2024 Dante Sabatier. All rights reserved.",
        asar: true,
    },
    rebuildConfig: {},
    makers: [
        {
            name: "@electron-forge/maker-squirrel",
            config: {
                language: 1033,
                manufacturer: "Dante Sabatier"
            }
        }
    ],
    plugins: [
        {
            name: "@electron-forge/plugin-auto-unpack-natives",
            config: {},
        },
        // Fuses are used to enable/disable various Electron functionality
        // at package time, before code signing the application
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
