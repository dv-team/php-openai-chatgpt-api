<?php

namespace DvTeam\ChatGPT\Functions\Function\Types;

use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\GPTProperty;

class GPTObjectProperty implements GPTProperty {
	public function __construct(
		public readonly string $name,
		public readonly ?string $description,
		public readonly GPTProperties $properties,
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
	 *     type: 'object',
	 *     name: string,
	 *     description?: string,
	 *     properties: GPTProperties,
	 * }
	 */
	public function jsonSerialize(): array {
		$data = [
			'type' => 'object',
			'name' => $this->name,
			'required' => $this->required,
		];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->properties !== null) {
			$data['properties'] = $this->properties;
		}

		return $data;
	}
}
