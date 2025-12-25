<?php

namespace DvTeam\ChatGPT\Functions;

use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaFormat;
use JsonSerializable;

/**
 * @phpstan-type TFunction array{
 *      name: string,
 *      description: string,
 *      parameters: object{
 *          type: 'object',
 *          properties: object,
 *          required?: string[]
 *      },
 *      returns?: object
 *  }
 */
class GPTFunction implements JsonSerializable {
	/**
	 * @param string $name
	 * @param string $description
	 * @param GPTProperties $properties
	 * @param null|JsonSchemaFormat $returns Optional return schema (JSON schema) for the function's return value
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly GPTProperties $properties,
		public readonly ?JsonSchemaFormat $returns = null,
	) {}

	/**
	 * @return TFunction
	 */
	public function jsonSerialize(): array {
		$parameters = [
			'type' => 'object',
			'properties' => $this->properties->jsonSerialize(),
			'additionalProperties' => false,
		];

		$required = [];
		foreach($this->properties->properties as $property) {
			if($property->isRequired()) {
				$required[] = $property->getName();
			}
		}

		if(count($required)) {
			$parameters['required'] = $required;
		}

		/** @var TFunction $data */
		$data = [
			'name' => $this->name,
			'description' => $this->description,
			'parameters' => (object) $parameters,
		];

		if($this->returns !== null) {
			$data['returns'] = (object) $this->returns->schema;
			if($this->returns->strict !== null) {
				$data['returns']->strict = $this->returns->strict;
			}
		}

		return $data;
	}
}
