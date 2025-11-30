<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMNanoNoReasoning implements ChatModelName {
	public function __toString(): string {
		return 'gpt-5-nano';
	}
}
