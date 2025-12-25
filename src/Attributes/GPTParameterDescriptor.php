<?php

namespace DvTeam\ChatGPT\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class GPTParameterDescriptor {
	/**
	 * @param array<string, mixed> $definition
	 */
	public function __construct(
		public readonly array $definition,
	) {}
}
