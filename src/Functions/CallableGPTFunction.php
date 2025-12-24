<?php

namespace DvTeam\ChatGPT\Functions;

use DvTeam\ChatGPT\Functions\Function\GPTProperties;

/**
 * Represents a function definition that is backed by a real PHP callable.
 */
class CallableGPTFunction extends GPTFunction {
	public function __construct(
		string $name,
		string $description,
		GPTProperties $properties,
		/** @var callable */
		private readonly mixed $callable,
	) {
		parent::__construct($name, $description, $properties);
	}

	/**
	 * @return callable
	 */
	public function getCallable(): callable {
		return $this->callable;
	}
}
