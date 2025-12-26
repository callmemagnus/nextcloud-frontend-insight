<?php

namespace OCA\FrontEndInsight\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int setType(string $type)
 * @method string getType()
 * @method int setMessage(string $message)
 * @method string getMessage()
 * @method int setUseragent(string $useragent)
 * @method string getUseragent()
 * @method int setUrl(string $url)
 * @method string getUrl()
 * @method int setTimestamp(int $timestamp)
 * @method int getTimestamp()
 * @method int setStack(string $stack)
 * @method string getStack()
 * @method int setFile(string $file)
 * @method string getFile()
 */
class Event extends Entity implements JsonSerializable {

	protected $timestamp;
	protected $type;
	protected $useragent;
	protected $url;
	protected $message;
	protected $stack;
	protected $file;

	public function __construct() {
		$this->addType('timestamp', 'integer');
		$this->addType('type', 'string');
		$this->addType('useragent', 'string');
		$this->addType('url', 'string');
		$this->addType('message', 'string');
		$this->addType('stack', 'string');
		$this->addType('file', 'string');
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'timestamp' => $this->timestamp,
			'type' => $this->type,
			'useragent' => $this->useragent,
			'url' => $this->url,
			'message' => $this->message,
			'stack' => $this->stack,
			'file' => $this->file
		];
	}
}
