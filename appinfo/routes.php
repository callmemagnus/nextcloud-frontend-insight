<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		// API
		['name' => 'Api#getEvents', 'url' => '/api/1.0/events', 'verb' => 'GET'],
		['name' => 'Api#getStats', 'url' => '/api/1.0/stats', 'verb' => 'GET'],
		['name' => 'Api#purgeAllEvents', 'url' => '/api/1.0/events/purge', 'verb' => 'DELETE'],
		['name' => 'Api#preflighted_cors',
			'url' => '/api/1.0/{path}',
			'verb' => 'OPTIONS',
			'requirements' => ['path' => '.+']
		],

		// Admin settings
		['name' => 'Settings#get', 'url' => '/settings', 'verb' => 'GET'],
		['name' => 'Settings#save', 'url' => '/settings', 'verb' => 'POST'],
	]
];
