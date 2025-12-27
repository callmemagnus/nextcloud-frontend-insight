<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import { eventData } from "./stores/eventData";
    import { translate } from "@nextcloud/l10n";
    import {APP_ID} from "../constants.js";

    let totalItems = $state(0);

    $effect(() => {
        const unsub = eventData.subscribe((data) => {
            totalItems = data.totalItems;
        });
        return () => unsub();
    });
</script>

<div class="mwb-buttons">
    <button class="mwb-danger" disabled={totalItems === 0}>
        {translate(APP_ID, "Purge all events")}
    </button>
</div>

<style>
    .mwb-buttons {
        margin-top: 16px;
    }

    .mwb-danger {
        padding: 8px 16px;
        background-color: var(--color-error);
        color: white;
        border: 1px solid var(--color-error);
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .mwb-danger:hover:not(:disabled) {
        background-color: var(--color-error-hover, #c9302c);
        border-color: var(--color-error-hover, #c9302c);
    }

    .mwb-danger:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        background-color: var(--color-background-dark, #888);
        border-color: var(--color-background-dark, #888);
    }
</style>
