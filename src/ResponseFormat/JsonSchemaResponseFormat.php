<?php

namespace DvTeam\ChatGPT\ResponseFormat;

use JsonSerializable;

class JsonSchemaResponseFormat implements JsonSerializable {
	/**
	 * @param mixed[] $schema
	 */
	public function __construct(
		public readonly array $schema
	) {}
	
	/**
	 * @return array{type: string, json_schema: mixed[]}
	 */
	public function jsonSerialize(): array {
		return [
			'type' => 'json_schema',
			'json_schema' => $this->schema
		];
	}
}