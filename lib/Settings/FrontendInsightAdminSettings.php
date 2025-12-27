<?php

declare(strict_types=1);

namespace OCA\FrontendInsight\Settings;

use OCA\FrontendInsight\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class FrontendInsightAdminSettings implements ISettings {
	public function __construct(
		private IRequest $request,
		private IAppConfig $appConfig,
		private ISession $session,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
	) {
	}

	public function getForm(): TemplateResponse {
		$collectErrors = $this->appConfig->getValueBool(Application::APP_ID, 'collect_errors', true);
		$collectUnhandled = $this->appConfig->getValueBool(Application::APP_ID, 'collect_unhandled_rejections', true);

		$saved = ($this->session->get(Application::APP_ID . '_settings_saved') === '1');
		if ($saved) {
			$this->session->remove(Application::APP_ID . '_settings_saved');
		}

		return new TemplateResponse(
			Application::APP_ID,
			'settings-admin',
			[
				'collect_errors' => $collectErrors,
				'collect_unhandled_rejections' => $collectUnhandled,
				'saved' => $saved,
				'retention_hours' => $this->appConfig->getValueInt(Application::APP_ID, 'retention_hours', 24 * 30),
				'available_groups' => $this->getAllGroups(),
				'selected_groups' => $this->getSelectedGroups(),
				'current_user_groups' => $this->getCurrentUserGroups(),
			],
			'blank'
		);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}

	/**
	 * @return array<int, array{gid:string, displayName:string}>
	 */
	private function getAllGroups(): array {
		$result = [];
		try {
			foreach ($this->groupManager->search('', 0, -1) as $group) {
				/** @var \OCP\IGroup $group */
				$result[] = [
					'gid' => $group->getGID(),
					'displayName' => method_exists($group, 'getDisplayName') ? $group->getDisplayName() : $group->getGID(),
				];
			}
		} catch (\Throwable $e) {
			// fallback empty
		}
		return $result;
	}

	/**
	 * @return string[]
	 */
	private function getSelectedGroups(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, 'allowed_groups', '[]');
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			return array_values(array_filter(array_map('strval', $decoded)));
		}
		return [];
	}

	/**
	 * @return array<int, string>
	 */
	private function getCurrentUserGroups(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [];
		}
		$groups = [];
		try {
			foreach ($this->groupManager->getUserGroups($user) as $group) {
				$groups[] = method_exists($group, 'getDisplayName') ? $group->getDisplayName() : $group->getGID();
			}
		} catch (\Throwable $e) {
			// ignore
		}
		return $groups;
	}
}
