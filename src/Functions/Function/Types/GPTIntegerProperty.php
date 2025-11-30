<?php

namespace DvTeam\ChatGPT\Functions\Function\Types;

use DvTeam\ChatGPT\Functions\Function\GPTProperty;

class GPTIntegerProperty implements GPTProperty {
	/**
	 * @param string $name
	 * @param null|string $description
	 * @param null|int $minimum
	 * @param null|int $maximum
	 * @param null|int $exclusiveMinimum
	 * @param null|int $exclusiveMaximum
	 * @param null|int $multipleOf
	 * @param null|int[] $enum
	 * @param bool $required
	 */
	public function __construct(
		public readonly string $name,
		public readonly ?string $description,
		public readonly ?int $minimum = null,
		public readonly ?int $maximum = null,
		public readonly ?int $exclusiveMinimum = null,
		public readonly ?int $exclusiveMaximum = null,
		public readonly ?int $multipleOf = null,
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
	 *     type: 'integer',
	 *     description?: string,
	 *     minimum?: int,
	 *     maximum?: int,
	 *     exclusiveMinimum?: int,
	 *     exclusiveMaximum?: int,
	 *     enum?: int[],
	 *     multipleOf?: int
	 * }
	 */
	public function jsonSerialize(): array {
		$data = [
			'type' => 'integer',
		];

		if($this->description !== null) {
			$data['description'] = $this->description;
		}

		if($this->minimum !== null) {
			$data['minimum'] = $this->minimum;
		}

		if($this->maximum !== null) {
			$data['maximum'] = $this->maximum;
		}

		if($this->exclusiveMinimum !== null) {
			$data['exclusiveMinimum'] = $this->exclusiveMinimum;
		}

		if($this->exclusiveMaximum !== null) {
			$data['exclusiveMaximum'] = $this->exclusiveMaximum;
		}

		if($this->multipleOf !== null) {
			$data['multipleOf'] = $this->multipleOf;
		}

		if($this->enum !== null) {
			$data['enum'] = $this->enum;
		}

		return $data;
	}
}
