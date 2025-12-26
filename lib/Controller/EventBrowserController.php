<?php

declare(strict_types=1);

namespace OCA\FrontEndInsight\Controller;

use OCA\FrontEndInsight\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class EventBrowserController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly IAppConfig $appConfig,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @return string[]
	 */
	private function getAllowedGroups(): array {
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

	private function isUserAllowed(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		$allowed = $this->getAllowedGroups();
		if ($allowed === []) {
			return false;
		}
		foreach ($allowed as $gid) {
			if ($this->groupManager->isInGroup($user->getUID(), $gid)) {
				return true;
			}
		}
		return false;
	}


	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse|JSONResponse {
		if (!$this->isUserAllowed()) {
			return new JSONResponse(['message' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new TemplateResponse(Application::APP_ID, 'event-browser-page');
	}
}
