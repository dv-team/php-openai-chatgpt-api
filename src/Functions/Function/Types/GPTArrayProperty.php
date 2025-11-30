<?php

namespace DvTeam\ChatGPT\Functions\Function\Types;

use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\GPTProperty;

class GPTArrayProperty implements GPTProperty {
	public function __construct(
		public readonly string $name,
		public readonly ?string $description,
		public readonly ?GPTProperties $properties,
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
	 *     type: 'array',
	 *     name: string,
	 *     description?: string,
	 *     items?: GPTProperties,
	 * }
	 */
	public function jsonSerialize(): array {
		$data = [
			'type' => 'array',
			'name' => $this->name,
		];

		if($this->description !== null) {
			$data['description'] = $this->description;
		}

		if($this->properties !== null) {
			$data['items'] = $this->properties;
		}

		return $data;
	}
}
