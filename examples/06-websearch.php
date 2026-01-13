<?php

declare(strict_types = 1);

use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

/** @var \DvTeam\ChatGPT\ChatGPT $chat */
$chat = require __DIR__ . '/_bootstrap.php';

$search = $chat->webSearch(
	query: sprintf('What are the ten biggest Cities in Germany in %d?', (new DateTimeImmutable())->format('Y')),
	userLocation: ['type' => 'approximate', 'country' => 'US'],
	model: new LLMSmallReasoning(effort: ReasoningEffort::Medium),
);

$response = $chat->chat(
	context: [
		ChatInput::mk('Answer the question using the web search result only.'),
		ChatInput::mk($search->getFirstText()),
	],
	responseFormat: new JsonSchemaResponseFormat([
		'type' => 'object',
		'properties' => [
			'cities' => [
				'type' => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'name' => ['type' => 'string'],
						'population' => ['type' => 'integer'],
					],
				],
			],
		],
	])
);

print_r($response->firstChoice()->objResult);

