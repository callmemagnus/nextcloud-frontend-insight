<?php

declare(strict_types=1);

namespace OCA\FrontEndInsight\Controller;

use OCA\FrontEndInsight\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;

class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IURLGenerator $urlGenerator,
		private ISession $session,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function get(): JSONResponse {
		if (!$this->isAdmin()) {
			return new JSONResponse(['message' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new JSONResponse([
			'collect_errors' => $this->appConfig->getValueBool(Application::APP_ID, 'collect_errors', true),
			'collect_unhandled_rejections' => $this->appConfig->getValueBool(Application::APP_ID, 'collect_unhandled_rejections', true),
			'retention_hours' => $this->appConfig->getValueInt(Application::APP_ID, 'retention_hours', 24 * 30),
			'allowed_groups' => $this->getAllowedGroups(),
		]);
	}

	private function isAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		return $this->groupManager->isAdmin($user->getUID());
	}

	/**
	 * @return string[]
	 */
	private function getAllowedGroups(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, 'allowed_groups', '[]');
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			return array_values(array_filter(array_map('strval', $decoded)));
		}
		return [];
	}

	public function save(): JSONResponse|RedirectResponse {
		if (!$this->isAdmin()) {
			return new JSONResponse(['message' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		$collectErrors = (string)($this->request->getParam('collect_errors', '')) !== '';
		$collectUnhandled = (string)($this->request->getParam('collect_unhandled_rejections', '')) !== '';
		$this->appConfig->setValueBool(Application::APP_ID, 'collect_errors', $collectErrors);
		$this->appConfig->setValueBool(Application::APP_ID, 'collect_unhandled_rejections', $collectUnhandled);

		$retention = (int)($this->request->getParam('retention_hours', '0'));
		if ($retention < 1) {
			$retention = 24 * 30; // default 30 days
		}
		$this->appConfig->setValueInt(Application::APP_ID, 'retention_hours', $retention);

		// groups
		$groupsParam = $this->request->getParam('allowed_groups');
		$groups = [];
		if (is_array($groupsParam)) {
			$groups = array_values(array_filter(array_map('strval', $groupsParam)));
		} elseif (is_string($groupsParam)) {
			$groups = array_values(array_filter(array_map('trim', explode(',', $groupsParam))));
		}
		$this->appConfig->setValueString(Application::APP_ID, 'allowed_groups', json_encode($groups));


		// Content negotiation: JSON if requested
		$accept = strtolower($this->request->getHeader('accept'));
		if (strpos($accept, 'application/json') !== false) {
			return new JSONResponse(['success' => true]);
		}

		// success feedback
		$this->session->set(Application::APP_ID . '_settings_saved', '1');

		// Otherwise, redirect back
		$referer = $this->request->getHeader('referer');
		if ($referer !== '') {
			return new RedirectResponse($referer);
		}
		$adminUrl = $this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => Application::APP_ID]);
		return new RedirectResponse($adminUrl);
	}
}
