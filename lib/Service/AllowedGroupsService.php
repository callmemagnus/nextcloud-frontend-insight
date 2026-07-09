<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FrontendInsight\Service;

use OCA\FrontendInsight\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;

class AllowedGroupsService {
	public function __construct(
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
	) {
	}

	/**
	 * @return string[]
	 */
	public function getAllowedGroups(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, 'allowed_groups', '[]');
		$decoded = json_decode($raw, true);
		$allowed = [];
		if (is_array($decoded)) {
			$allowed = array_values(array_filter(array_map('strval', $decoded)));
		}
		// Fallback: if no explicit allowed groups configured but there is exactly one group named "admin",
		// treat it as allowed by default (to match settings UI and navigation behavior)
		if ($allowed === []) {
			try {
				$all = $this->groupManager->search('', 0, -1);
				if (count($all) === 1) {
					/** @var \OCP\IGroup $g */
					$g = $all[0];
					$gid = $g->getGID();
					$dn = method_exists($g, 'getDisplayName') ? $g->getDisplayName() : $gid;
					if (strtolower($gid) === 'admin' || strtolower($dn) === 'admin') {
						$allowed = [$gid];
					}
				}
			} catch (\Throwable $e) {
				// ignore
			}
		}
		return $allowed;
	}
}
