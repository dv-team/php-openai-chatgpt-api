<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMSmallNoReasoning implements ChatModelName {
	public function __toString(): string {
		return 'gpt-4.1-mini';
	}
}