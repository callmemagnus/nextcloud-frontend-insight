<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import { translate } from "@nextcloud/l10n";
    import {APP_ID} from "../constants.js";

    const { cursor, limit, total, onPrev, onNext } = $props<{
        cursor: number;
        limit: number;
        total: number;
        onPrev: () => void;
        onNext: () => void;
    }>();

    const page = $derived(Math.ceil(cursor / limit));
    const totalPages = $derived(
        Math.max(1, Math.ceil(total / Math.max(1, limit))),
    );
    const canPrev = $derived(page > 0);
    const canNext = $derived(page + 1 < totalPages);
</script>

{#if totalPages > 1}
    <div class="mwb-pager">
        <button
            type="button"
            class="mwb-btn"
            disabled={!canPrev}
            onclick={onPrev}
            aria-label={translate(APP_ID, "Previous page")}>← {translate(APP_ID, "Prev")}</button
        >
        <span class="info">{translate(APP_ID, "Page {page} / {total}", { page: page + 1, total: totalPages })}</span>
        <button
            type="button"
            class="mwb-btn"
            disabled={!canNext}
            onclick={onNext}
            aria-label={translate(APP_ID, "Next page")}>{translate(APP_ID, "Next")} →</button
        >
    </div>
{/if}

<style>
    .mwb-pager {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 12px;
    }

    .info {
        font-size: 0.9rem;
        color: var(--color-main-text, #333);
        min-width: 120px;
        text-align: center;
    }
</style>
