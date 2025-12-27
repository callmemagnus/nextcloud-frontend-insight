<?php

declare(strict_types=1);

namespace OCA\FrontendInsight\Controller;

use OCA\FrontendInsight\Db\EventMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\Exception;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends Controller {


	public function __construct(
		$appName,
		IRequest $request,
		private EventMapper $eventMapper,
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
		parent::__construct($appName, $request);
	}

	private function isAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		return $this->groupManager->isAdmin($user->getUID());
	}

	#[CORS]
	public function getEvents(?string $url, ?int $limit = 20, ?string $q = null, ?string $sort = 'timestamp', ?string $dir = 'desc', ?int $cursor = null): array {

		try {
			// enforce maximum limit of 100
			$effectiveLimit = max(1, min(100, $limit ?? 20));
			$result = $this->eventMapper->getEvents(0, $effectiveLimit, $url, $q, $sort ?? 'timestamp', $dir ?? 'desc', $cursor);
			$count = $this->eventMapper->countEvents($url, $q);
			$this->logger->debug('Returning events', ['events' => $result]);

		} catch (Exception $e) {
			$result = [];
			$count = 0;
		}

		return [
			'limit' => $effectiveLimit,
			'cursor' => $cursor ?? 0,
			'totalItems' => $count,
			'values' => $result
		];
	}

	#[CORS]
	public function getStats(): array {
		try {
			$first = $this->eventMapper->getEarliestEventTimestamp();
			$latest = $this->eventMapper->getLatestEventTimestamp();
			$byType = $this->eventMapper->getTypeStats();
		} catch (Exception $e) {
			$first = null;
			$latest = null;
			$byType = [];
		}
		return [
			'firstTimestamp' => $first,
			'latestTimestamp' => $latest,
			'byType' => (object)$byType,
		];
	}

	#[CORS]
	public function purgeAllEvents(): JSONResponse {
		if (!$this->isAdmin()) {
			return new JSONResponse(['message' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		try {
			$this->eventMapper->deleteAllEvents();
			$this->logger->info('All FrontendInsight events purged by admin user', [
				'user' => $this->userSession->getUser()?->getUID(),
			]);
			return new JSONResponse(['success' => true]);
		} catch (Exception $e) {
			$this->logger->error('Failed to purge all events: ' . $e->getMessage(), [
				'exception' => $e,
			]);
			return new JSONResponse(
				['message' => 'Failed to purge events'],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}
}
