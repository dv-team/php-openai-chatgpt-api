<?php

namespace DvTeam\ChatGPT;

use Stringable;

class OpenAIToken implements Stringable {
	public function __construct(
		public readonly string $token,
	) {}

	public function __toString(): string {
		return $this->token;
	}
}
