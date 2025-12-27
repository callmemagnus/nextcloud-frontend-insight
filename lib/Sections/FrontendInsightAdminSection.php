<?php

declare(strict_types=1);

namespace OCA\FrontendInsight\Sections;

use OCA\FrontendInsight\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class FrontendInsightAdminSection implements IIconSection {
	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
	) {
	}

	public function getID(): string {
		// section id must be unique; use the app id
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10n->t('Front-end Insight');
	}

	public function getPriority(): int {
		// lower means higher placement; keep it relatively high up
		return 10;
	}

	public function getIcon(): string {
		// absolute URL to an icon; expects img/app.svg to exist
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}
}
