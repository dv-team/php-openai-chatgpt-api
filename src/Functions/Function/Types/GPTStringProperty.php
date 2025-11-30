<?php

namespace DvTeam\ChatGPT\Functions\Function\Types;

use DvTeam\ChatGPT\Functions\Function\GPTProperty;

class GPTStringProperty implements GPTProperty {
	/**
	 * @param string $name
	 * @param string|null $description
	 * @param int|null $minLength
	 * @param int|null $maxLength
	 * @param string|null $pattern
	 * @param null|'email'|'uri'|'date-time' $format
	 * @param string[]|null $enum
	 * @param bool $required
	 */
	public function __construct(
		public readonly string $name,
		public readonly ?string $description = null,
		public readonly ?int $minLength = null,
		public readonly ?int $maxLength = null,
		public readonly ?string $pattern = null,
		public readonly ?string $format = null,
		public readonly ?array $enum = null,
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
	 *     type: 'string',
	 *     description?: string,
	 *     minLength?: int,
	 *     maxLength?: int,
	 *     pattern?: string,
	 *     format?: 'email'|'uri'|'date-time',
	 *     enum?: string[],
	 * }
	 */
	public function jsonSerialize(): array {
		$data = [
			'type' => 'string',
		];

		if($this->description !== null) {
			$data['description'] = $this->description;
		}

		if($this->minLength !== null) {
			$data['minLength'] = $this->minLength;
		}

		if($this->maxLength !== null) {
			$data['maxLength'] = $this->maxLength;
		}

		if($this->pattern !== null) {
			$data['pattern'] = $this->pattern;
		}

		if($this->format !== null) {
			$data['format'] = $this->format;
		}

		if($this->enum !== null) {
			$data['enum'] = $this->enum;
		}

		return $data;
	}
}
