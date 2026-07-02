<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMSmallNoReasoning implements ChatModelName {
	public function __toString(): string {
		return 'gpt-5.5-mini';
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
