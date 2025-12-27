<?php

declare(strict_types=1);

namespace OCA\FrontendInsight\BackgroundJob;

use OCA\FrontendInsight\AppInfo\Application;
use OCA\FrontendInsight\Db\EventMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class PurgeOldEvents extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private IAppConfig $appConfig,
		private EventMapper $eventMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		// run hourly by default
		$this->setInterval(60 * 60);
	}

	protected function run($argument): void {
		try {
			$hours = $this->appConfig->getValueInt(Application::APP_ID, 'retention_hours', 24 * 30);
			if ($hours <= 0) {
				// disabled or invalid, skip
				return;
			}
			$nowMs = (int)floor(microtime(true) * 1000);
			$threshold = $nowMs - ($hours * 60 * 60 * 1000);
			$this->eventMapper->deleteEventsOlderThan($threshold);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to purge old FrontendInsight events: ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}
}
