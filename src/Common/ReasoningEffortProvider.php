<?php

namespace DvTeam\ChatGPT\Common;

use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;

interface ReasoningEffortProvider {
	public function reasoningEffort(): ?ReasoningEffort;
}
