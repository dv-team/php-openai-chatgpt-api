<?php

namespace DvTeam\ChatGPT\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class GPTCallableDescriptor {
	public function __construct(
		public readonly ?string $name,
		public readonly string $description,
	) {}
}
