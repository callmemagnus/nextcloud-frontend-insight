<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FrontendInsight\Service;

use OCA\FrontendInsight\AppInfo\Application;
use OCP\IGroupManager;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class ErrorNotificationService {
	public function __construct(
		private IManager $notificationManager,
		private IGroupManager $groupManager,
		private AllowedGroupsService $allowedGroupsService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Notify every user in the configured allowed groups that new front-end
	 * errors have been reported. Intended to be called once, at the moment
	 * the reported-events count transitions from zero to non-zero.
	 */
	public function notifyNewErrors(): void {
		$allowedGroups = $this->allowedGroupsService->getAllowedGroups();
		if ($allowedGroups === []) {
			$this->logger->debug('Frontend Insight: no allowed groups resolved, skipping new-error notification', [
				'app' => Application::APP_ID,
			]);
			return;
		}

		$notifiedUserIds = [];
		foreach ($allowedGroups as $gid) {
			$group = $this->groupManager->get($gid);
			if ($group === null) {
				$this->logger->debug('Frontend Insight: allowed group not found, skipping', [
					'app' => Application::APP_ID,
					'gid' => $gid,
				]);
				continue;
			}
			foreach ($group->getUsers() as $user) {
				$uid = $user->getUID();
				if (isset($notifiedUserIds[$uid])) {
					continue;
				}
				$notifiedUserIds[$uid] = true;

				$notification = $this->notificationManager->createNotification();
				$notification->setApp(Application::APP_ID)
					->setUser($uid)
					->setDateTime(new \DateTime())
					->setObject('errors', 'new')
					->setSubject('new_errors');
				$this->notificationManager->notify($notification);
			}
		}
		$this->logger->debug('Frontend Insight: sent new-error notification', [
			'app' => Application::APP_ID,
			'userCount' => count($notifiedUserIds),
		]);
	}

	/**
	 * Clear any pending "new errors" notification for the given user, e.g.
	 * once they've opened the event browser and no longer need to be nudged.
	 */
	public function dismissNewErrorsNotification(string $uid): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setObject('errors', 'new')
			->setSubject('new_errors');
		$this->notificationManager->markProcessed($notification);
	}
}
