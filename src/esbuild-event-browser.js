import * as esbuild from "esbuild";
import sveltePlugin from "esbuild-svelte";

esbuild
    .build({
        entryPoints: ["src/event-browser/App.svelte"],
        mainFields: ["svelte", "browser", "module", "main"],
        conditions: ["svelte", "browser"],
        bundle: true,
        outfile: "js/event-browser.js",
        plugins: [
            sveltePlugin({
                compilerOptions: { customElement: true },
            })
        ],
        logLevel: "info",
    })
    .catch(() => process.exit(1));