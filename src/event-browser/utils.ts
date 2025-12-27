/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {generateUrl} from "@nextcloud/router";

const basePath = generateUrl('/')
const url = window.location.href.split('/')
const base = url.slice(0, 4).join('/')


export function removeBase(url: string) : string{
    return url.replace(
        new RegExp(`^${base}`),
        ''
    )
}
