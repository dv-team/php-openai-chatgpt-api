<?php

namespace DvTeam\ChatGPT\Functions;

use DvTeam\ChatGPT\Functions\CallableGPTFunction;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @phpstan-import-type TFunction from GPTFunction
 *
 * @implements IteratorAggregate<GPTFunction>
 */
class GPTFunctions implements JsonSerializable, IteratorAggregate {
	/** @var GPTFunction[] */
	public readonly array $functions;
	/** @var array<string, callable> */
	private array $callableMap = [];

	public function __construct(
		GPTFunction ...$functions
	) {
		$this->functions = $functions;

		foreach($functions as $function) {
			if($function instanceof CallableGPTFunction) {
				$this->callableMap[$function->name] = $function->getCallable();
			}
		}
	}

	/**
	 * @return Traversable<GPTFunction>
	 */
	public function getIterator(): Traversable {
		yield from $this->functions;
	}

	/**
	 * @return TFunction[]
	 */
	public function jsonSerialize(): array {
		$functions = [];
		foreach($this->functions as $function) {
			$functions[] = $function->jsonSerialize();
		}

		return $functions;
	}

	public function getCallable(string $name): ?callable {
		return $this->callableMap[$name] ?? null;
	}
}
