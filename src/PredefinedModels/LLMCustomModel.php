<?php

namespace DvTeam\ChatGPT\PredefinedModels;

use DvTeam\ChatGPT\Common\ChatModelName;

class LLMCustomModel implements ChatModelName {
	public function __construct(
		public readonly string $model,
		public readonly ?ReasoningEffort $effort = null
	) {}

	public function __toString(): string {
		return $this->model;
	}
}
