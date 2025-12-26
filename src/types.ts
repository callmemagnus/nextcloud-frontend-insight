/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export interface Event {
  id?: number;
  timestamp: number;
  type: string;
  useragent: string;
  url: string;
  message?: string;
  stack?: string;
  file?: string;
}
