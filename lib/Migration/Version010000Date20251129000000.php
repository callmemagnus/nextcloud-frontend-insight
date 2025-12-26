<?php

declare(strict_types=1);

namespace OCA\FrontEndInsight\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20251129000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	/**
	 * @psalm-suppress UndefinedDocblockClass
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('fe_errors')) {
			$table = $schema->createTable('fe_errors');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('timestamp', Types::INTEGER, [
				'notnull' => true
			]);
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('useragent', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('url', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('message', Types::STRING, [
				'notnull' => false,
				'length' => 300,
			]);
			$table->addColumn('stack', Types::TEXT, [
				'notnull' => false
			]);
			$table->addColumn('file', Types::STRING, [
				'notnull' => false,
				'length' => 500,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
