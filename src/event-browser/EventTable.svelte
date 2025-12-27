<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import type { Event } from "../types";
    import { generateUrl } from "@nextcloud/router";
    import axios from "@nextcloud/axios";
    import { onMount } from "svelte";
    import { removeBase } from "./utils.js";
    import DateDisplay from "./DateDisplay.svelte";
    import Pagination from "./Pagination.svelte";
    import CopyText from "./CopyText.svelte";
    import type { DateFormat, TimezoneMode } from "./stores/dateDisplay";
    import {
        dateDisplaySettings,
        setFormat,
        setTimezone,
        showNextcloudOption,
    } from "./stores/dateDisplay";
    import { setTotalItems } from "./stores/eventData";
    import { translate } from "@nextcloud/l10n";
    import {APP_ID} from "../constants.js";

    const url = generateUrl(`/apps/${APP_ID}/api/1.0/events`);
    let cursor = $state(0);
    let limit = $state(10);
    let totalItems = $state(0);
    let rows = $state<Event[]>();
    let loading = $state(false);

    // Row expansion state
    let expanded = $state<Record<number, boolean>>({});

    function toggleDetails(index: number) {
        const result = { ...expanded } as Record<number, boolean>;
        result[index] = !Boolean(expanded[index]);
        expanded = result;
    }

    // Server-side filter & sort controls
    let filterText = $state("");
    type SortColumn =
        | "timestamp"
        | "type"
        | "useragent"
        | "url"
        | "stack"
        | "file"
        | "message";
    let sortColumn = $state<SortColumn>(
        (localStorage.getItem("fei_sort_col") as SortColumn) || "timestamp",
    );
    type SortDir = "asc" | "desc";
    let sortDir = $state<SortDir>(
        (localStorage.getItem("fei_sort_dir") as SortDir) || "desc",
    );

    // Controls state (synced with global store)
    let tz = $state<TimezoneMode>("utc");
    let fmt = $state<DateFormat>("iso");

    $effect(() => {
        const unsub = dateDisplaySettings.subscribe((s) => {
            tz = s.timezone;
            fmt = s.format;
        });
        return () => unsub();
    });

    $effect(() => {
        setTotalItems(totalItems);
    });

    const showNC = $state<boolean>(showNextcloudOption());

    type Stats = {
        firstTimestamp: number | null;
        byType: Record<string, number>;
    };
    let stats = $state<Stats | null>(null);

    async function load() {
        loading = true;
        const params = new URLSearchParams();
        params.set("cursor", cursor.toString());
        params.set("limit", limit.toString());
        if (filterText) params.set("q", filterText);
        if (sortColumn) params.set("sort", sortColumn);
        if (sortDir) params.set("dir", sortDir);
        const result = await (axios.get(`${url}?${params}`) as Promise<{
            data: {
                values: Event[];
                totalItems: number;
                cursor: number;
                limit: number;
            };
        }>);
        totalItems = result.data.totalItems ?? totalItems;
        limit = result.data.limit ?? limit;
        rows = result.data.values.map((e: Event) => ({
            ...e,
            // keep timestamp as number; only format when rendering
            url: removeBase(e.url),
        }));
        expanded = {};
        loading = false;
    }

    function triggerReload() {
        // reset to first page when filter/sort changes
        cursor = 0;
        localStorage.setItem("fei_sort_col", sortColumn);
        localStorage.setItem("fei_sort_dir", sortDir);
        load();
    }

    function clearSelection() {
         if (window.getSelection) {
            const sel = window.getSelection();
            sel?.removeAllRanges();
        }
    }

    onMount(() => {
        load();
    });
</script>

<details class="table-controls" style="margin-bottom: 16px;">
    <summary>{translate(APP_ID, "Filters")}</summary>
    <form
        onsubmit={(e) => {
            e.preventDefault();
            triggerReload();
        }}
    >
        <label>
            {translate(APP_ID, "Filter")}:
            <input
                type="text"
                placeholder={translate(APP_ID, "Search (excludes timestamps)")}
                bind:value={filterText}
            />
        </label>
        <label>
            {translate(APP_ID, "Sort by")}:
            <select bind:value={sortColumn}>
                <option value="timestamp">{translate(APP_ID, "Timestamp")}</option>
                <option value="type">{translate(APP_ID, "Type")}</option>
                <option value="message">{translate(APP_ID, "Message")}</option>
                <option value="url">{translate(APP_ID, "URL")}</option>
                <option value="file">{translate(APP_ID, "File")}</option>
            </select>
        </label>
        <label>
            {translate(APP_ID, "Order")}:
            <select bind:value={sortDir}>
                <option value="desc">{translate(APP_ID, "Desc")}</option>
                <option value="asc">{translate(APP_ID, "Asc")}</option>
            </select>
        </label>
        <button type="submit">{translate(APP_ID, "Apply")}</button>
    </form>
</details>

{#if loading}
    <p>{translate(APP_ID, "Loading…")}</p>
{:else if rows && rows.length === 0}
    <div class="empty-state">
        <p>{translate(APP_ID, "No results")}</p>
        <small>{translate(APP_ID, "There are no events to display.")}</small>
    </div>
{:else if rows}
    <div class="">
        <table class="mwb-table">
            <thead>
                <tr>
                    <th class="col-expander"></th>
                    <th>{translate(APP_ID, "Timestamp")}</th>
                    <th>{translate(APP_ID, "Type")}</th>
                    <th>{translate(APP_ID, "Message")}</th>
                    <th>{translate(APP_ID, "URL")}</th>
                </tr>
            </thead>
            <tbody>
                {#each rows as event, i}
                    <tr
                        class:mwb-is-expanded={expanded[i]}
                        ondblclick={() => {
                            toggleDetails(i);
                            clearSelection();
                        }}
                    >
                        <td class="col-expander">
                            <button
                                type="button"
                                class="mwb-icon-expander"
                                aria-label={expanded[i]
                                    ? translate(APP_ID, "Hide details")
                                    : translate(APP_ID, "Show details")}
                                onclick={() => toggleDetails(i)}
                            ></button>
                        </td>
                        <td class="col-ts">
                            <DateDisplay timestamp={event.timestamp} />
                        </td>
                        <td class="col-type">{event.type}</td>
                        <td class="col-msg">
                            <CopyText
                                text={event.message ?? ""}
                                title={event.message ?? ""}
                            />
                        </td>
                        <td class="col-url">
                            <CopyText
                                text={event.url ?? ""}
                                title={event.url ?? ""}
                            />
                        </td>
                    </tr>
                    {#if expanded[i]}
                        <tr class="details">
                            <td colspan="7">
                                <div class="details-grid">
                                    <div>
                                        <strong>{translate(APP_ID, "User Agent")}</strong>
                                        <div class="mono">
                                            {event.useragent}
                                        </div>
                                    </div>
                                    <div>
                                        <strong>{translate(APP_ID, "Stack")}</strong>
                                        <pre class="mono">{event.stack}</pre>
                                    </div>
                                    <div>
                                        <strong>{translate(APP_ID, "File")}</strong>
                                        <CopyText
                                            text={event.file ?? "-"}
                                            title={event.file ?? "-"}
                                            monospace={true}
                                        />
                                    </div>
                                </div>
                            </td>
                        </tr>
                    {/if}
                {/each}
            </tbody>
        </table>
        <div class="mwb-table-tools">
            <Pagination
                {cursor}
                {limit}
                total={totalItems}
                onPrev={() => {
                    if (cursor > 0) {
                        cursor -= limit;
                        load();
                    }
                }}
                onNext={() => {
                    if (cursor + limit < totalItems) {
                        cursor += limit;
                        load();
                    }
                }}
            />
            <div class="controls">
                <label>
                    {translate(APP_ID, "Timezone")}:
                    <select
                        bind:value={tz}
                        onchange={(e) =>
                            setTimezone(
                                (e.target as HTMLSelectElement).value as any,
                            )}
                    >
                        <option value="utc">{translate(APP_ID, "UTC")}</option>
                        <option value="browser">{translate(APP_ID, "Browser")}</option>
                        {#if showNC}
                            <option value="nextcloud">{translate(APP_ID, "Nextcloud")}</option>
                        {/if}
                    </select>
                </label>
                <label>
                    {translate(APP_ID, "Format")}:
                    <select
                        bind:value={fmt}
                        onchange={(e) =>
                            setFormat(
                                (e.target as HTMLSelectElement).value as any,
                            )}
                    >
                        <option value="iso">{translate(APP_ID, "ISO")}</option>
                        <option value="human">{translate(APP_ID, "Human")}</option>
                        <option value="local">{translate(APP_ID, "Local")}</option>
                        <option value="ago">{translate(APP_ID, "Ago")}</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
{/if}

<style>
    .mwb-table-tools {
        display: flex;
        flex-direction: row-reverse;
        justify-content: space-between;
    }

    .mwb-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background-color: var(--color-main-background, #fff);
        border: 1px solid var(--color-border, #ddd);
        border-radius: 8px;
        overflow: hidden;
    }

    .mwb-table thead th,
    .mwb-table tbody td {
        transition: background 0.12s ease-in-out;
    }
    .mwb-table thead th {
        position: sticky;
        top: 0;
        background-color: var(--color-main-background, #f7f7f7);
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 10px 12px;
        border-bottom: 1px solid var(--color-border, #ddd);
    }
    .mwb-table tbody td {
        padding: 8px 12px;
        /* vertical-align: top; */
        border-bottom: 1px solid var(--color-border, #eee);
        font-size: 0.9rem;
        color: var(--color-main-text);
        background-color: var(--color-main-background, #f0f6ff);
    }
    .mwb-table tbody tr:hover {
        background: var(--color-main-background, #f0f6ff);
    }

    .mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
            "Liberation Mono", "Courier New", monospace;
        font-size: 0.85rem;
    }

    .col-ts {
        white-space: nowrap;
    }
    .col-type {
        font-weight: 600;
    }
    .col-url {
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .col-msg {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* Expander triangle */
    .col-expander {
        width: 28px;
    }
    .mwb-icon-expander {
        display: inline-block;
        position: relative;
        width: 16px;
        min-height: 16px;
        height: 16px;
        background: transparent;
        padding: 0;
        margin: 0;
        cursor: pointer;
    }
    .mwb-icon-expander::before {
        content: "";
        position: absolute;
        left: 4px;
        top: 3px;
        width: 0;
        height: 0;
        border-left: 8px solid var(--color-main-text, #333);
        border-top: 6px solid transparent;
        border-bottom: 6px solid transparent;
        transform: rotate(0deg);
        transition: transform 0.12s ease-in-out;
    }
    tr.mwb-is-expanded .mwb-icon-expander::before {
        transform: rotate(90deg);
    }
    .details td {
        background-color: var(--color-main-background, #fafafa);
    }
    .details-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }
    @media (min-width: 900px) {
        .details-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    .table-controls {
        display: block;
    }
    .table-controls form,
    .table-controls label {
        display: flex;
        gap: 8px;
        align-items: center;
        margin: 6px 0;
    }
    .table-controls input[type="text"] {
        min-width: 240px;
        padding: 6px 8px;
        border: 1px solid var(--color-border, #ddd);
        border-radius: 4px;
    }
    .table-controls select {
        padding: 6px 8px;
        border: 1px solid var(--color-border, #ddd);
        border-radius: 4px;
    }
    .table-controls button[type="submit"] {
        padding: 6px 10px;
        border: 1px solid var(--color-primary, #2b7cff);
        background: var(--color-primary, #2b7cff);
        color: #fff;
        border-radius: 4px;
        cursor: pointer;
    }
</style>
