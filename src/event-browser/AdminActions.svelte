<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import {eventData, triggerRefresh} from "./stores/eventData";
    import {translate} from "@nextcloud/l10n";
    import {APP_ID} from "../constants.js";
    import {loadState} from "@nextcloud/initial-state";
    import {generateUrl} from "@nextcloud/router";
    import axios from "@nextcloud/axios";

    let totalItems = $state(0);
    let isPurging = $state(false);
    const isAdmin = loadState(APP_ID, "is_admin", false);

    $effect(() => {
        const unsub = eventData.subscribe((data) => {
            totalItems = data.totalItems;
        });
        return () => unsub();
    });

    async function handlePurgeAll() {
        const confirmed = confirm(
            translate(APP_ID, "Are you sure you want to permanently delete all events? This action cannot be undone.")
        );

        if (!confirmed) {
            return;
        }

        isPurging = true;
        try {
            await axios.delete(generateUrl(`/apps/${APP_ID}/api/1.0/events/purge`));
            console.log("All events have been purged successfully");
            // Refresh the event data
            triggerRefresh();
        } catch (error) {
            console.error("Failed to purge events:", error);
            alert(translate(APP_ID, "Failed to purge events. Please try again."));
        } finally {
            isPurging = false;
        }
    }
</script>

{#if isAdmin}
    <div class="mwb-buttons">
        <button
                class="mwb-danger"
                disabled={totalItems === 0 || isPurging}
                onclick={handlePurgeAll}
        >
            {isPurging ? translate(APP_ID, "Purging...") : translate(APP_ID, "Purge all events")}
        </button>
    </div>
{/if}

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
