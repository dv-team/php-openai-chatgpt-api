<?php

namespace DvTeam\ChatGPT\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class GPTMethod {
	public function __construct(
		public readonly string $description,
	) {}
}
