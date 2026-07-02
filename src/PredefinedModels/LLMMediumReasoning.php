<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMMediumReasoning implements ChatModelName {
	public function __construct(
		public readonly ReasoningEffort $effort
	) {}

	public function __toString(): string {
		return 'gpt-5.5';
	}

	public function supportsTemperature(): bool {
		return false;
	}

	public function supportsTopP(): bool {
		return false;
	}

	public function supportsMaxTokens(): bool {
		return true;
	}
}
