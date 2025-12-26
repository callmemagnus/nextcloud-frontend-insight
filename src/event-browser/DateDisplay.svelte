<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import type { DateDisplaySettings } from './stores/dateDisplay';
    import { dateDisplaySettings } from './stores/dateDisplay';
    import { nextcloudTimezoneId } from './stores/dateDisplay';
    import { translatePlural, translate } from '@nextcloud/l10n';

    const { timestamp, forceFormat } = $props<{ timestamp: number; forceFormat?: import('./stores/dateDisplay').DateFormat }>();

    // Runes mode: local reactive state and subscription
    let settings = $state<DateDisplaySettings>({ timezone: 'utc', format: 'iso' });

    $effect(() => {
        const unsub = dateDisplaySettings.subscribe((v) => { settings = v; });
        return () => unsub();
    });

    function pad2(n: number): string { return n.toString().padStart(2, '0'); }

    function relFmt(ts: number): string {
        const now = Date.now();
        const diffMs = now - ts; // positive = past
        const past = diffMs >= 0;
        const absMs = Math.abs(diffMs);

        const SEC = 1000;
        const MIN = SEC * 60;
        const HOUR = MIN * 60;
        const DAY = HOUR * 24;
        const MONTH = DAY * 30; // approximate
        const YEAR = DAY * 365; // approximate

        let value: number;
        let unitStr: string;

        if (absMs < MIN) {
            value = past ? Math.floor(absMs / SEC) : Math.ceil(absMs / SEC);
            unitStr = translatePlural('frontendinsight', '%n second', '%n seconds', value, { n: value });
        } else if (absMs < HOUR) {
            value = past ? Math.floor(absMs / MIN) : Math.ceil(absMs / MIN);
            unitStr = translatePlural('frontendinsight', '%n minute', '%n minutes', value, { n: value });
        } else if (absMs < DAY) {
            value = past ? Math.floor(absMs / HOUR) : Math.ceil(absMs / HOUR);
            unitStr = translatePlural('frontendinsight', '%n hour', '%n hours', value, { n: value });
        } else if (absMs < MONTH) {
            value = past ? Math.floor(absMs / DAY) : Math.ceil(absMs / DAY);
            unitStr = translatePlural('frontendinsight', '%n day', '%n days', value, { n: value });
        } else if (absMs < YEAR) {
            value = past ? Math.floor(absMs / MONTH) : Math.ceil(absMs / MONTH);
            unitStr = translatePlural('frontendinsight', '%n month', '%n months', value, { n: value });
        } else {
            value = past ? Math.floor(absMs / YEAR) : Math.ceil(absMs / YEAR);
            unitStr = translatePlural('frontendinsight', '%n year', '%n years', value, { n: value });
        }

        return past
            ? translate('frontendinsight', '{time} ago', { time: unitStr })
            : translate('frontendinsight', 'in {time}', { time: unitStr });
    }

    function fmtPartsInZone(ts: number, timeZone?: string | undefined) {
        const d = new Date(ts);
        const dtf = new Intl.DateTimeFormat(undefined, {
            timeZone,
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: false,
        });
        const parts = dtf.formatToParts(d);
        const get = (t: string) => parts.find(p => p.type === t)?.value ?? '';
        return {
            year: get('year'),
            month: get('month'),
            day: get('day'),
            hour: get('hour'),
            minute: get('minute'),
        };
    }

    function fmt(ts: number, s: DateDisplaySettings): string {
        const effectiveFormat = forceFormat ?? s.format;
        const d = new Date(ts);
        // ISO format is always UTC to be canonical
        if (effectiveFormat === 'iso') {
            return d.toISOString();
        }
        if (effectiveFormat === 'ago') {
            return relFmt(ts);
        }
        // 'local' uses Intl with date/time styles
        if (effectiveFormat === 'local') {
            const timeZone = s.timezone === 'utc' ? 'UTC' : (s.timezone === 'nextcloud' ? nextcloudTimezoneId ?? undefined : undefined);
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'short', timeStyle: 'medium', timeZone
            }).format(d);
        }
        // human format: YYYY-MM-DD HH:mm (when effectiveFormat === 'human')
        const timeZone = s.timezone === 'utc' ? 'UTC' : (s.timezone === 'nextcloud' ? nextcloudTimezoneId ?? undefined : undefined);
        const p = fmtPartsInZone(ts, timeZone);
        return `${p.year}-${p.month}-${p.day} ${p.hour}:${p.minute}`;
    }

    const formatted = $derived(fmt(timestamp, settings));
</script>

<span>{formatted}</span>
