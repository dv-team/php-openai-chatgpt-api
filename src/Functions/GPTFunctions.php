<?php

namespace DvTeam\ChatGPT\Functions;

use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<GPTFunction>
 */
class GPTFunctions implements JsonSerializable, IteratorAggregate {
	/** @var GPTFunction[] */
	public readonly array $functions;

	public function __construct(
		GPTFunction ...$functions
	) {
		$this->functions = $functions;
	}

	/**
	 * @return Traversable<GPTFunction>
	 */
	public function getIterator(): Traversable {
		yield from $this->functions;
	}

	/**
	 * @return array<mixed>
	 */
	public function jsonSerialize(): array {
		$functions = [];
		foreach($this->functions as $function) {
			$functions[] = $function->jsonSerialize();
		}

		return $functions;
	}
}
