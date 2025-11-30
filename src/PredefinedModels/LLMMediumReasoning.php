<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMMediumReasoning implements ChatModelName {
	public function __construct(
		public readonly ReasoningEffort $effort
	) {}

	public function __toString(): string {
		return 'gpt-5.1';
	}
}
