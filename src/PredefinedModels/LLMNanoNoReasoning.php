<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMNanoNoReasoning implements ChatModelName {
	public function __toString(): string {
		return 'gpt-5.5-nano';
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
}
