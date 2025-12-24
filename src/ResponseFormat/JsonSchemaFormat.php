<?php

namespace DvTeam\ChatGPT\ResponseFormat;

use JsonSerializable;

/**
 * @phpstan-type TJsonSchema array{type: object, properties: list<mixed>}|object{type: object, properties: list<mixed>}
 */
class JsonSchemaFormat implements JsonSerializable {
	/**
	 * @param mixed[] $schema
	 */
	public function __construct(
		public readonly array $schema,
		public readonly bool $strict = false,
	) {}

	/**
	 * @return array{type: string, json_schema: mixed[]}
	 */
	public function jsonSerialize(): array {
		return [
			'type' => 'json_schema',
			'json_schema' => [
				'name' => 'Response',
				'schema' => $this->schema,
				'strict' => $this->strict
			],
		];
	}
}
