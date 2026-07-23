<?php

namespace DvTeam\ChatGPT\Common;

use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;

/**
 * Reconstructed model configuration from a serialized conversation session.
 *
 * @internal
 */
final class PersistedChatModel implements ChatModelName, ReasoningEffortProvider {
	public function __construct(
		private readonly string $name,
		private readonly bool $temperatureSupported,
		private readonly bool $topPSupported,
		private readonly bool $maxTokensSupported,
		private readonly ?ReasoningEffort $effort,
	) {}

	public function __toString(): string {
		return $this->name;
	}

	public function supportsTemperature(): bool {
		return $this->temperatureSupported;
	}

	public function supportsTopP(): bool {
		return $this->topPSupported;
	}

	public function supportsMaxTokens(): bool {
		return $this->maxTokensSupported;
	}

	public function reasoningEffort(): ?ReasoningEffort {
		return $this->effort;
	}
}
