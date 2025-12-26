/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { writable } from 'svelte/store';
import { loadState } from '@nextcloud/initial-state';

const APP_ID = 'frontendinsight';

export type TimezoneMode = 'utc' | 'browser' | 'nextcloud';
export type DateFormat = 'iso' | 'human' | 'local' | 'ago';

export interface DateDisplaySettings {
    timezone: TimezoneMode;
    format: DateFormat;
}

export const nextcloudTimezoneId = loadState(APP_ID, 'user_timezone', null) as string | null;
export const nextcloudTimezoneOffsetMin = loadState(APP_ID, 'user_timezone_offset_min', null) as number | null;

export function showNextcloudOption(): boolean {
    if (nextcloudTimezoneOffsetMin === null) return true; // unknown -> show
    // JS getTimezoneOffset returns minutes difference from local to UTC, positive west of UTC.
    // Convert to minutes east of UTC for comparison by negating.
    const browserOffsetMin = -new Date().getTimezoneOffset();
    return nextcloudTimezoneOffsetMin !== browserOffsetMin;
}

// Global store for date display preferences (Svelte store used with runes via subscribe)
export const dateDisplaySettings = writable<DateDisplaySettings>({
    timezone: 'utc',
    format: 'human',
});

export function setTimezone(mode: TimezoneMode) {
    dateDisplaySettings.update((s) => ({ ...s, timezone: mode }));
}

export function setFormat(format: DateFormat) {
    dateDisplaySettings.update((s) => ({ ...s, format }));
}
