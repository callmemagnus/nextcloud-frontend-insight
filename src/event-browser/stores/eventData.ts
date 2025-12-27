/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { writable } from "svelte/store";

export const eventData = writable({
    totalItems: 0,
    refreshTrigger: 0,
});

export function setTotalItems(count: number) {
    eventData.update((state) => ({
        ...state,
        totalItems: count,
    }));
}

export function triggerRefresh() {
    eventData.update((state) => ({
        ...state,
        refreshTrigger: state.refreshTrigger + 1,
    }));
}
