<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FrontendInsight\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Server;
use OCP\Util;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'frontend_insight';

	/**
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(
		array $urlParams = [],
	) {
		parent::__construct(self::APP_ID, $urlParams);
		$dispatcher = $this->getContainer()->get(IEventDispatcher::class);
		//$initialStateService = $this->getContainer()->get(IInitialState::class);

		$dispatcher->addListener(AddContentSecurityPolicyEvent::class, function (AddContentSecurityPolicyEvent $event) {
			$appConfig = Server::get(IAppConfig::class);
			$url = $appConfig->getValueString(Application::APP_ID, 'url');
			if ($url !== '') {
				$policy = new ContentSecurityPolicy();
				$policy->addAllowedScriptDomain($url);
				$policy->addAllowedConnectDomain($url);
				$event->addPolicy($policy);
			}
		});
		$dispatcher->addListener(BeforeTemplateRenderedEvent::class, function (BeforeTemplateRenderedEvent $event) {
			$appConfig = Server::get(IAppConfig::class);
			$initialState = $this->getContainer()->get(IInitialState::class);
			$userSession = Server::get(IUserSession::class);
			$groupManager = Server::get(IGroupManager::class);

			// Provide initial state for configuration UI
			$sendError = $appConfig->getValueBool(self::APP_ID, 'collect_errors', true);
			$sendUnhandled = $appConfig->getValueBool(self::APP_ID, 'collect_unhandled_rejections', true);

			// Check if current user is admin
			$isAdmin = false;
			$user = $userSession->getUser();
			if ($user !== null) {
				$isAdmin = $groupManager->isAdmin($user->getUID());
			}

			// Legacy keys
			$initialState->provideInitialState('collect_errors', $sendError);
			$initialState->provideInitialState('collect_unhandled_rejections', $sendUnhandled);
			$initialState->provideInitialState('is_admin', $isAdmin);
		});
	}

	public function register(IRegistrationContext $context): void {
		// nothing to do here
	}

	/**
	 * @throws NotFoundExceptionInterface
	 * @throws ContainerExceptionInterface
	 */
	/**
	 * @psalm-suppress UndefinedClass
	 */
	public function boot(IBootContext $context): void {
		$appConfig = Server::get(IAppConfig::class);
		$urlGenerator = Server::get(IURLGenerator::class);
		// $contentSecurityPolicyManager = Server::get(IContentSecurityPolicyManager::class);
		//$contentSecurityPolicyNonceManager = Server::get(IContentSecurityPolicyManager::class::class);


		Util::addInitScript(self::APP_ID, 'client');


		//		$linkToJs = $urlGenerator->linkToRoute('frontend_insight.Scripts.script', [
		//			'v' => $appConfig->getValueString(self::APP_ID, 'cachebuster', '0'),
		//		]);
		//
		//		Util::addHeader(
		//			'script',
		//			[
		//				'src' => $linkToJs,
		//				/** @psalm-suppress UndefinedClass */ 'nonce' => \OC::$server->getContentSecurityPolicyNonceManager()->getNonce()
		//			],
		//			''
		//		);


		//        $this->initialState->provideLazyInitialState('collect_error', );


		// Provide initial state for timezone (Nextcloud user preference)
		try {
			/** @var IInitialState $initialState */
			$initialState = $this->getContainer()->get(IInitialState::class);
			$userSession = Server::get(IUserSession::class);
			$user = $userSession->getUser();
			$tzId = 'UTC';
			if ($user !== null) {
				$cfg = \OC::$server->getConfig();
				$tzId = (string)$cfg->getUserValue($user->getUID(), 'core', 'timezone', 'UTC');
			}
			$tz = new \DateTimeZone($tzId ?: 'UTC');
			$offsetMin = (int)((new \DateTime('now', $tz))->getOffset() / 60);
			$initialState->provideInitialState('user_timezone', $tz->getName());
			$initialState->provideInitialState('user_timezone_offset_min', $offsetMin);
		} catch (\Throwable $e) {
			// ignore problems with initial-state seeding
		}

		// whitelist the URL to allow loading JS from this external domain
		//        $url = $appConfig->getValueString(self::APP_ID, 'url');
		//        if ($url !== '') {
		//            $policy = new ContentSecurityPolicy();
		//            $policy->addAllowedScriptDomain($url);
		//            //				$policy->addAllowedImageDomain($url);
		//            //				$policy->addAllowedConnectDomain($url);
		//            $contentSecurityPolicyManager->addDefaultPolicy($policy);
		//        }

		// Add navigation entry for allowed users
		try {
			$navigation = Server::get(INavigationManager::class);
			$userSession = Server::get(IUserSession::class);
			$groupManager = Server::get(IGroupManager::class);
			$appConfig = Server::get(IAppConfig::class);
			$user = $userSession->getUser();
			if ($user !== null) {
				$raw = $appConfig->getValueString(self::APP_ID, 'allowed_groups', '[]');
				$allowed = @json_decode($raw, true);
				$allowed = is_array($allowed) ? array_values(array_filter(array_map('strval', $allowed))) : [];
				// Fallback: if no explicit allowed groups configured, but there is exactly one group named "admin",
				// treat it as allowed by default (to match settings UI behavior)
				if (empty($allowed)) {
					try {
						$allGroups = $groupManager->search('', 0, -1);
						if (count($allGroups) === 1) {
							/** @var \OCP\IGroup $g */
							$g = $allGroups[0];
							$gid = $g->getGID();
							$dn = method_exists($g, 'getDisplayName') ? $g->getDisplayName() : $gid;
							$isAdmin = (strtolower($gid) === 'admin') || (strtolower($dn) === 'admin');
							if ($isAdmin) {
								$allowed = [$gid];
							}
						}
					} catch (\Throwable $e) {
						// ignore
					}
				}
				$visible = false;
				if (!empty($allowed)) {
					foreach ($allowed as $gid) {
						if ($groupManager->isInGroup($user->getUID(), $gid)) {
							$visible = true;
							break;
						}
					}
				}
				if ($visible) {
					$navigation->add(function () use ($urlGenerator) {
						return [
							'id' => self::APP_ID,
							'order' => 50,
							'href' => $urlGenerator->linkToRoute('frontend_insight.EventBrowser.index'),
							'icon' => $urlGenerator->imagePath(self::APP_ID, 'app-white.svg'),
							'name' => 'Frontend Insight',
						];
					});
				}
			}
		} catch (\Throwable $e) {
			// ignore navigation errors
		}
	}


}
