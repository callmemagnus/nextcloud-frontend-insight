<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FrontendInsight\Controller;

use OCA\FrontendInsight\AppInfo\Application;
use OCA\FrontendInsight\Db\EventMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Response;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class ScriptsController extends Controller {
	/**
	 * @var false|resource
	 */
	private $file;
	private string $reportUrl;

	/**
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private EventMapper $eventMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->file = fopen(__DIR__ . '/../../js/client.js', 'r');

		$urlGenerator = Server::get(IURLGenerator::class);
		$this->reportUrl = $urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.Scripts.reportError');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @return DataDownloadResponse
	 */
	#[FrontpageRoute(verb: 'GET', url: '/client.js')]
	public function script(): DataDownloadResponse {
		// $enabled = $this->appConfig->getA
		$jsCode = 'console.log("Could not load FEI script");';

		if ($this->file) {
			$content = fread($this->file, filesize(__DIR__ . '/../../js/client.js'));
			if ($content) {
				$content = str_replace('__REPORT_URL__', $this->reportUrl, $content);
				$content = str_replace('__APP_NAME__', Application::APP_ID, $content);
				$jsCode = $content;
			}
		}
		return new DataDownloadResponse($jsCode, 'script', 'text/javascript');
	}

	/**
	 * @throws Exception
	 */
	#[UserRateLimit(limit: 20, period: 60)]
	#[AnonRateLimit(limit: 10, period: 60)]
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/report/error')]
	public function reportError(
		int $timestamp,
		string $type,
		string $useragent,
		string $url,
		?string $message,
		?string $stack,
		?string $file,
	): Response {
		$this->logger->debug('Reporting event ' . $message, [
			'timestamp' => $timestamp,
			'type' => $type,
			'url' => $url,
			'useragent' => $useragent,
			'message' => $message,
			'stack' => $stack,
			'file' => $file
		]);
		$this->eventMapper->addEvent(timestamp: $timestamp, type: $type, url: $url, useragent: $useragent, message: $message, stack: $stack, file: $file);
		return new Response(status: Http::STATUS_NO_CONTENT);
	}
}
