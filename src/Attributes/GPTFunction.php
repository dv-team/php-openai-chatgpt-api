<?php

namespace DvTeam\ChatGPT\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class GPTFunction {
	public function __construct(
		public readonly string $description,
	) {}
}
