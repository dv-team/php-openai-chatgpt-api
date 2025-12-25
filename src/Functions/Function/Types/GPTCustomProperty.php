<?php

namespace DvTeam\ChatGPT\Functions\Function\Types;

use DvTeam\ChatGPT\Functions\Function\GPTProperty;

class GPTCustomProperty implements GPTProperty {
	/**
	 * @param object{name: string} $structureDefinition
	 */
	public function __construct(
		public object $structureDefinition,
		private bool $required
	) {}

	public function getName(): string {
		return $this->structureDefinition->name;
	}

	public function isRequired(): bool {
		return $this->required;
	}

	public function jsonSerialize(): object {
		return $this->structureDefinition;
	}
}
