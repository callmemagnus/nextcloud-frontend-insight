/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios';
import type {Event} from '../types.js';
import {Throttle} from "./Throttle.js";
import {generateUrl} from "@nextcloud/router";
import {loadState} from '@nextcloud/initial-state';
import {APP_ID} from "../constants.js";


const sendError = loadState(APP_ID, 'collect_errors', true);
const sendUnhandledRejection = loadState(APP_ID, 'collect_unhandled_rejections', true)

console.log(`${APP_ID} is enabled, sending error: ${sendError}, unhandledrejection: ${sendUnhandledRejection})`)

const throttle = new Throttle(send, 10, 10)

async function send(payload: Event) {
    try {
        await axios.post(generateUrl(`/apps/${APP_ID}/report/error`), payload)
    } catch (e) {
        // ignore
    }
}

async function plan(e: ErrorEvent | PromiseRejectionEvent) {
    const payload: Event = {
        timestamp: Date.now(),
        type: e.type,
        useragent: navigator.userAgent,
        url: window.location.href
    }

    if (e.type === 'unhandledrejection') {
        const ev = e as PromiseRejectionEvent
        payload.message = ev.reason.message
        payload.stack = ev.reason.stack
        if (ev.reason.filename?.length) {
            payload.file = `${ev.reason.filename}:${ev.reason.lineno}:${ev.reason.colno}`
        }
    } else if (e.type === 'error') {
        const ev = e as ErrorEvent
        payload.message = ev.message
        if (ev.filename?.length) {
            payload.file = `${ev.filename}:${ev.lineno}:${ev.colno}`
        }
    }

    await throttle.call(payload)
}

if (sendUnhandledRejection) {
    window.addEventListener('unhandledrejection', async function (e) {
        console.group('window.unhandledrejection')
        console.log(`Unhandled rejection caught and sent to server`, e.reason)
        console.log(e)
        console.groupEnd()

        plan(e)
    })
}

if (sendError) {
    window.addEventListener('error', async (e) => {
        console.group('window.error')
        console.log(`Error caught and sent to server`, e.message)
        console.log(e)
        console.groupEnd()

        plan(e)
    })
}
