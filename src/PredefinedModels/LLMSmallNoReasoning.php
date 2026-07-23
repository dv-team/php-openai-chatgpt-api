<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Common\ReasoningEffortProvider;

class LLMSmallNoReasoning implements ChatModelName, ReasoningEffortProvider {
	public function __toString(): string {
		return 'gpt-5.6-terra';
	}

	public function supportsTemperature(): bool {
		return true;
	}

	public function supportsTopP(): bool {
		return true;
	}

	public function supportsMaxTokens(): bool {
		return true;
	}

	public function reasoningEffort(): ReasoningEffort {
		return ReasoningEffort::None;
	}
}
