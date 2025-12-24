<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTIntegerProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaFormat;
use PHPUnit\Framework\TestCase;

class GPTFunctionTest extends TestCase {
	public function testReturnsSchemaIsSerialized(): void {
		$fn = new GPTFunction(
			name: 'add_one',
			description: 'Adds one to a number.',
			properties: new GPTProperties(
				new GPTIntegerProperty(name: 'value', description: 'The input value.', required: true)
			),
			returns: new JsonSchemaFormat([
				'type' => 'object',
				'properties' => [
					'result' => [
						'type' => 'integer',
					],
				],
				'required' => ['result'],
			])
		);

		$serialized = $fn->jsonSerialize();

		$this->assertArrayHasKey('returns', $serialized);
		$this->assertSame('object', $serialized['returns']->type ?? null);
		$this->assertSame('integer', $serialized['returns']->properties['result']['type'] ?? null);
	}
}
