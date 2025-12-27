<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import { translate } from "@nextcloud/l10n";
    import {APP_ID} from "../constants.js";

    const {
        text,
        title,
        monospace = false,
    } = $props<{
        text: string;
        title?: string;
        monospace?: boolean;
    }>();

    let copied = $state(false);
    let hideTimer: any = null;

    async function copy() {
        try {
            await navigator.clipboard.writeText(text);
            copied = true;
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => (copied = false), 1200);
        } catch (e) {
            // noop
        }
    }
</script>

<div class={`mwb-copy-wrap ${monospace ? "mono" : ""}`} title={title ?? text}>
    <span class="mwb-copy-text">{text}</span>
    {#if text && text.length}
        <button
            type="button"
            class="mwb-icon-copy"
            aria-label={translate(APP_ID, "Copy")}
            title={translate(APP_ID, "Copy")}
            onclick={copy}
        ></button>
    {/if}
    {#if copied}
        <div class="mwb-copied">{translate(APP_ID, "Copied!")}</div>
    {/if}
</div>

<style>
    .mwb-copy-wrap {
        position: relative;
        display: inline-flex;
        align-items: baseline;
        max-width: 100%;
    }
    .mwb-copy-text {
        flex-grow: 1;
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    .mwb-icon-copy {
        opacity: 0;
        transition: opacity 0.12s ease-in-out;
        padding: 5px;
        margin: 0 0 0 -15px !important;
        position: relative;
        height: 18px;

        /* position: absolute;
        right: 4px;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        border: 0;
        background: transparent;
        cursor: pointer;
        padding: 0; */
    }
    .mwb-copy-wrap:hover .mwb-icon-copy {
        opacity: 1;
    }

    /* Minimal copy icon using CSS */
    .mwb-icon-copy::before {
        content: "";
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid var(--color-text, #333);
        border-radius: 2px;
        position: relative;
        box-sizing: border-box;
        background: var(--color-background, #fff);
    }
    .mwb-icon-copy::after {
        content: "";
        position: absolute;
        width: 10px;
        height: 10px;
        border: 2px solid var(--color-text, #666);
        border-radius: 2px;
        left: 10px;
        top: 16px;
        background: var(--color-background, #fff);
        box-sizing: border-box;
    }

    .mwb-copied {
        position: absolute;
        right: 0;
        top: 2px;
        transform: translateY(-50%);
        background: #111;
        color: #fff;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        white-space: nowrap;
        pointer-events: none;
    }

    .mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
            "Liberation Mono", "Courier New", monospace;
        font-size: 0.85rem;
    }
</style>
