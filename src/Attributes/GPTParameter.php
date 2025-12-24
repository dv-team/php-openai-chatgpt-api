<?php

namespace DvTeam\ChatGPT\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class GPTParameter {
	public function __construct(
		public readonly string $description,
	) {}
}
