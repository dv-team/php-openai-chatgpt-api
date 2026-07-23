<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Common\ReasoningEffortProvider;

class LLMSmallReasoning implements ChatModelName, ReasoningEffortProvider {
	public function __construct(
		public readonly ReasoningEffort $effort
	) {}

	public function __toString(): string {
		return 'gpt-5.6-terra';
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

	public function reasoningEffort(): ReasoningEffort {
		return $this->effort;
	}
}
