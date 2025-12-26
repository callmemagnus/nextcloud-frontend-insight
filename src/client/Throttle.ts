/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export class Throttle<T> {
    private history: { timestamp: number }[] = []
    constructor(private caller: (payload: T) => Promise<void>, private limit = 5, private timeInSec = 10, ) {
    }

    call(data: T) {
        this.history = this.history.filter(e => e.timestamp > Date.now() - this.timeInSec * 1000)
        if (this.history.length >= this.limit) {
            return
        }
        this.history.push({timestamp: Date.now()})
        return this.caller(data)
    }
}
