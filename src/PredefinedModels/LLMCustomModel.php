<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Common\ReasoningEffortProvider;

class LLMCustomModel implements ChatModelName, ReasoningEffortProvider {
	public function __construct(
		public readonly string $model,
		public readonly ?ReasoningEffort $effort = null
	) {}

	public function __toString(): string {
		return $this->model;
	}

	public function supportsTemperature(): bool {
		return $this->effort === null;
	}

	public function supportsTopP(): bool {
		return $this->effort === null;
	}

	public function supportsMaxTokens(): bool {
		return true;
	}

	public function reasoningEffort(): ?ReasoningEffort {
		return $this->effort;
	}
}
