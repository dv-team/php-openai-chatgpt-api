<?php

declare(strict_types = 1);

use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\Response\ChatResponse;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

/** @var \DvTeam\ChatGPT\ChatGPT $chat */
$chat = require __DIR__ . '/_bootstrap.php';

$schema = new JsonSchemaResponseFormat([
	'type' => 'object',
	'properties' => [
		'items' => [
			'type' => 'array',
			'items' => ['type' => 'integer'],
			'minItems' => 1,
		],
	],
	'additionalProperties' => false,
]);

/** @var ChatResponse<object{items: int[]}> $response */
$response = $chat->chat(
	context: [ChatInput::mk('Return the numbers 1..5 in JSON: {"items":[1,2,3,4,5]}')],
	responseFormat: $schema,
);

print_r($response->firstChoice()->objResult);

