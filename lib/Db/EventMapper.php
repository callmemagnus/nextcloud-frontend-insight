<?php

namespace OCA\FrontEndInsight\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Event>
 */
class EventMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'fe_errors', Event::class);
	}

	/**
	 * @param int $page
	 * @param int $limit
	 * @return Event[]
	 * @throws Exception
	 */
	public function getEvents(int $page, int $limit, ?string $url, ?string $q, string $sort, string $dir, ?int $startAt = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName);

		if ($url) {
			$qb->andWhere($qb->expr()->like('url', $qb->createNamedParameter('%' . $url . '%')));
		}
		if ($q !== null && $q !== '') {
			$param = $qb->createNamedParameter('%' . $q . '%');
			$expr = $qb->expr();
			$qb->andWhere(
				$expr->orX(
					$expr->like('type', $param),
					$expr->like('useragent', $param),
					$expr->like('url', $param),
					$expr->like('stack', $param),
					$expr->like('file', $param),
					$expr->like('message', $param),
				)
			);
		}

		$allowed = ['timestamp','type','useragent','url','stack','file','message'];
		$sortCol = in_array($sort, $allowed, true) ? $sort : 'timestamp';
		$sortDir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

		$offset = ($startAt !== null && $startAt >= 0) ? $startAt : ($page * $limit);
		$qb->orderBy($sortCol, $sortDir)
			->setFirstResult($offset)
			->setMaxResults($limit);

		return $qb->executeQuery()->fetchAll();
	}

	/**
	 * @throws Exception
	 */
	public function countEvents(?string $url, ?string $q): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->tableName);
		if ($url) {
			$qb->andWhere($qb->expr()->like('url', $qb->createNamedParameter('%' . $url . '%')));
		}
		if ($q !== null && $q !== '') {
			$param = $qb->createNamedParameter('%' . $q . '%');
			$expr = $qb->expr();
			$qb->andWhere(
				$expr->orX(
					$expr->like('type', $param),
					$expr->like('useragent', $param),
					$expr->like('url', $param),
					$expr->like('stack', $param),
					$expr->like('file', $param),
					$expr->like('message', $param),
				)
			);
		}

		return (int)$qb->executeQuery()->fetchOne();
	}

	/**
	 * @throws Exception
	 */
	public function addEvent(
		int $timestamp,
		string $type,
		string $url,
		string $useragent,
		?string $message,
		?string $stack,
		?string $file,
	): void {
		$event = new Event();
		$event->setTimestamp($timestamp);
		$event->setType($type);
		$event->setUrl($url);
		$event->setUseragent($useragent);
		if ($message) {
			$event->setMessage($message);
		}
		if ($stack) {
			$event->setStack($stack);
		}
		if ($file) {
			$event->setFile($file);
		}
		$this->insert($event);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getEvent(int $id) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @throws Exception
	 */
	public function deleteEventsOlderThan(int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function deleteEvent(int $id) {
		try {
			$event = $this->getEvent($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return null;
		}
		$this->delete($event);
	}


	public function getEarliestEventTimestamp(): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('MIN(timestamp)'))
			->from($this->getTableName());
		$val = $qb->executeQuery()->fetchOne();
		if ($val === false || $val === null) {
			return null;
		}
		return (int)$val;
	}

	public function getLatestEventTimestamp(): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('MAX(timestamp)'))
			->from($this->getTableName());
		$val = $qb->executeQuery()->fetchOne();
		if ($val === false || $val === null) {
			return null;
		}
		return (int)$val;
	}

	/**
	 * Returns per-type statistics: count and oldest timestamp (since when)
	 */
	/**
	 * @return array<string, int>
	 */
	public function getTypeStats(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('type')
			->addSelect($qb->createFunction('COUNT(*)' . 'as count'))
			->from($this->getTableName())
			->groupBy('type');
		$rows = $qb->executeQuery()->fetchAll();
		$stats = [];

		foreach ($rows as $row) {

			$type = $row['type'];
			if ($type === null) {
				continue;
			}
			$norm = strtolower(trim((string)$type));
			$cntRaw = $row['count'];
			$cnt = (int)$cntRaw;

			if ($cnt <= 0) {
				continue;
			}
			$stats[$norm] = $cnt;
		}
		return $stats;
	}



	public function getInterestingFacts(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('url')
			->from($this->getTableName())
			->setMaxResults(10);
		$urls = $qb->executeQuery()->fetchAll();

		$unique_url = [];
		foreach ($urls as $url) {
			$p = parse_url($url);
			$u = $p['scheme'] . '://' . $p['host'];
			if (isset($p['port']) && !in_array($p['port'], [80, 443], true)) {
				$u = $u . ':' . $p['port'];
			}
			$u = $u . '/' . $p['path'];

			if (!$unique_url[$u]) {
				$unique_url[$u] = 0;
			}
			$unique_url[$u]++;
		}

		return [
			'uniqueUrlCounts' => $unique_url
		];
	}
}
