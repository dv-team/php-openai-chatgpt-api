<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMMediumNoReasoning implements ChatModelName {
	public function __toString(): string {
		return 'gpt-4.1';
	}
}
