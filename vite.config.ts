// SPDX-FileCopyrightText: Magnus Anderssen <magnus@magooweb.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

import {svelte} from "@sveltejs/vite-plugin-svelte";
import {defineConfig} from "vite";
import cssInjectedByJsPlugin from "vite-plugin-css-injected-by-js";

// https://vitejs.dev/config/
export default defineConfig((config) => {
    const devMode = config.mode === "dev";

    return {
        ...config,
        plugins: [
            svelte(),
            //tailwindcss(),
            cssInjectedByJsPlugin()
        ],
        build: {
            sourcemap: devMode,
            minify: !devMode,
            outDir: "js",
            emptyOutDir: false,
            lib: {
                fileName: 'event-browser',
                formats: ["iife"],
                entry: "src/event-browser/main.ts",
                name: 'event-browser.js',
            },
        }
    };
});
