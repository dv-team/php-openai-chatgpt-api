<?php

namespace DvTeam\ChatGPT\Functions\Function;

use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<GPTProperty>
 */
class GPTProperties implements JsonSerializable, IteratorAggregate {
	/** @var GPTProperty[] */
	public readonly array $properties;

	public function __construct(
		GPTProperty ...$parameters
	) {
		$this->properties = $parameters;
	}
	
	/**
	 * @return Traversable<GPTProperty>
	 */
	public function getIterator(): Traversable {
		yield from $this->properties;
	}
	
	/**
	 * @return array<mixed>
	 */
	public function jsonSerialize(): array {
		$data = [];
		foreach ($this->properties as $property) {
			$data[$property->getName()] = $property->jsonSerialize();
		}
		return $data;
	}
}
