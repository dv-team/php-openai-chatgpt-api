<?php

namespace DvTeam\ChatGPT\Functions\Function\Types;

use DvTeam\ChatGPT\Functions\Function\GPTProperty;

class GPTNumberProperty implements GPTProperty {
	/**
	 * @param string $name
	 * @param string|null $description
	 * @param float|null $minimum
	 * @param float|null $maximum
	 * @param float|null $exclusiveMinimum
	 * @param float|null $exclusiveMaximum
	 * @param float[]|null $enum
	 * @param float|null $multipleOf
	 * @param bool $required
	 */
	public function __construct(
		public readonly string $name,
		public readonly ?string $description = null,
		public readonly ?float $minimum = null,
		public readonly ?float $maximum = null,
		public readonly ?float $exclusiveMinimum = null,
		public readonly ?float $exclusiveMaximum = null,
		public readonly ?array $enum = null,
		public readonly ?float $multipleOf = null,
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
	 *     type: 'number',
	 *     description?: string,
	 *     minimum?: float,
	 *     maximum?: float,
	 *     exclusiveMinimum?: float,
	 *     exclusiveMaximum?: float,
	 *     enum?: float[],
	 *     multipleOf?: float
	 * }
	 */
	public function jsonSerialize(): array {
		$data = [
			'type' => 'number',
		];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->minimum !== null) {
			$data['minimum'] = $this->minimum;
		}

		if ($this->maximum !== null) {
			$data['maximum'] = $this->maximum;
		}

		if ($this->exclusiveMinimum !== null) {
			$data['exclusiveMinimum'] = $this->exclusiveMinimum;
		}

		if ($this->exclusiveMaximum !== null) {
			$data['exclusiveMaximum'] = $this->exclusiveMaximum;
		}

		if ($this->enum !== null) {
			$data['enum'] = $this->enum;
		}

		if ($this->multipleOf !== null) {
			$data['multipleOf'] = $this->multipleOf;
		}

		return $data;
	}
}
