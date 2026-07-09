<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FrontendInsight\Notification;

use OCA\FrontendInsight\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Frontend Insight');
	}

	/**
	 * @throws UnknownNotificationException
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException('Notification not from this app');
		}
		if ($notification->getSubject() !== 'new_errors') {
			throw new UnknownNotificationException('Unknown notification subject');
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$eventBrowserLink = $this->urlGenerator->linkToRouteAbsolute(
			'frontend_insight.EventBrowser.index',
			['from' => 'notification'],
		);
		$notification->setParsedSubject($l->t('New front-end errors have been reported'))
			->setLink($eventBrowserLink)
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')));

		$viewAction = $notification->createAction();
		$viewAction->setLabel('view')
			->setParsedLabel($l->t('View errors'))
			->setPrimary(true)
			->setLink($eventBrowserLink, IAction::TYPE_WEB);
		$notification->addParsedAction($viewAction);

		return $notification;
	}
}
