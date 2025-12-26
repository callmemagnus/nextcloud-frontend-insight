<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { generateUrl } from '@nextcloud/router';
    import axios from '@nextcloud/axios';
    import DateDisplay from './DateDisplay.svelte';
    import { translatePlural, translate } from '@nextcloud/l10n';

    type Stats = { firstTimestamp: number | null, latestTimestamp: number | null, byType: Record<string, number> };

    let firstTimestamp = $state<number | null>(null);
    let latestTs = $state<number | null>(null);
    let byType = $state<Record<string, number> | null>(null);

    async function loadStats() {
        try {
            const su = generateUrl('/apps/frontendinsight/api/1.0/stats');
            const { data } = await (axios.get(su) as Promise<{ data: Stats }>);
            firstTimestamp = data.firstTimestamp;
            latestTs = data.latestTimestamp;
            byType = data.byType ?? {};
        } catch (e) {
            firstTimestamp = null;
            byType = {};
        }
    }

    let interval: any;
    onMount(() => {
        loadStats();
        interval = setInterval(loadStats, 30000);
    });
    onDestroy(() => clearInterval(interval));

    const entries = $derived(byType ? Object.entries(byType) : []);
</script>

{#if firstTimestamp || (entries && entries.length) || latestTs}
<div class="indicators">
    {#if firstTimestamp}
        <div class="indicator">
            <strong>{translate("frontendinsight", "First event")}:</strong>
            <DateDisplay timestamp={firstTimestamp} />
        </div>
    {/if}

    {#if latestTs}
        <div class="indicator">
            <strong>{translate("frontendinsight", "Latest event")}:</strong>
            <DateDisplay timestamp={latestTs} forceFormat="ago" />
        </div>
    {/if}

    {#if entries && entries.length > 0}
        {#each entries as [type, count]}
            <div class="indicator">
                <strong>{type}:</strong>
                <span>{translatePlural("frontendinsight", "%n event", "%n events", count, { n: count })}</span>
            </div>
        {/each}
    {/if}
</div>
{/if}

<style>
    .indicators {
        display: flex;
        gap: 12px;
        margin: 8px 0 16px;
        flex-wrap: wrap;
    }
    .indicator {
        background: var(--color-background-dark, #f5f5f5);
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 0.9rem;
    }
</style>
