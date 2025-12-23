<?php

namespace DvTeam\ChatGPT\Functions;

use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use JsonSerializable;

/**
 * @phpstan-type TFunction array{
 *      name: string,
 *      description: string,
 *      parameters: object{
 *          type: 'object',
 *          properties: object,
 *          required?: string[]
 *      }
 *  }
 */
class GPTFunction implements JsonSerializable {
	/**
	 * @param string $name
	 * @param string $description
	 * @param GPTProperties $properties
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly GPTProperties $properties,
	) {}

	/**
	 * @return TFunction
	 */
	public function jsonSerialize(): array {
		$required = [];
		foreach($this->properties->properties as $property) {
			if($property->isRequired()) {
				$required[] = $property->getName();
			}
		}

		$parameters = [
			'type' => 'object',
			'properties' => $this->properties->jsonSerialize(),
			'additionalProperties' => false,
		];

		if(count($required)) {
			$parameters['required'] = $required;
		}

		return [
			'name' => $this->name,
			'description' => $this->description,
			'parameters' => (object) $parameters
		];
	}
}
