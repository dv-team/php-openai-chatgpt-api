<?php

namespace DvTeam\ChatGPT\Functions\Function\Types;

use DvTeam\ChatGPT\Functions\Function\GPTProperty;

class GPTBooleanProperty implements GPTProperty {
	/**
	 * @param string $name
	 * @param string|null $description
	 * @param bool $required
	 */
	public function __construct(
		public readonly string $name,
		public readonly ?string $description = null,
		public readonly bool $required = false,
	) {}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function isRequired(): bool {
		return $this->required;
	}
	
	/**
	 * @return array{
	 *     type: 'boolean',
	 *     description?: string
	 * }
	 */
	public function jsonSerialize(): array {
		$data = [
			'type' => 'boolean',
		];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		return $data;
	}
}
