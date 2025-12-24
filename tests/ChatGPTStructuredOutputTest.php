<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\Response\ChatResponseChoice;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

/**
 * @phpstan-import-type TRequestData from ChatGPT
 */
class ChatGPTStructuredOutputTest extends TestCase {
	use TestTools;

	public function testChatReturnsStructuredOutputAndValidatesSchema(): void {
		$schema = [
			'type' => 'object',
			'properties' => [
				'items' => [
					'type' => 'array',
					'items' => [
						'type' => 'integer',
					],
					'minItems' => 1,
				],
			],
		];

		$firstResponseBody = (object) [
			'id' => 'resp_struct_123',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'msg_struct_1',
					'type' => 'message',
					'status' => 'completed',
					'content' => [
						(object) [
							'type' => 'output_text',
							'text' => '{"items":[1,1,1,1,2,2]}',
						],
					],
					'role' => 'assistant',
				],
			],
			'model' => 'gpt-5.1-2025-11-13',
		];

		$mockClient = new MockClient();
		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode($firstResponseBody))
		);

		$httpClient = Psr18HttpClient::create(
			client: $mockClient,
			requestFactory: new HttpFactory(),
			streamFactory: new HttpFactory()
		);

		$chat = new ChatGPT(
			token: new OpenAIToken('test-token'),
			httpPostClient: $httpClient
		);

		$responseFormat = new JsonSchemaResponseFormat($schema);
		$context = [new ChatInput('Erstelle mit eine Liste, wo die ersten vier Zahlen "1" sind und dann zwei mal 2 folgt.')];

		$response = $chat->chat($context, responseFormat: $responseFormat);

		$this->assertIsObject($response->firstChoice()->result);
		$this->assertSame([1, 1, 1, 1, 2, 2], $response->firstChoice()->objResult->items ?? []);

		$context = $response->firstChoice()->enhancedContext;

		$this->assertCount(2, $context);
		$this->assertInstanceOf(ChatInput::class, $context[0]);
		$this->assertInstanceOf(ChatResponseChoice::class, $context[1]);

		$secondResponseBody = (object) [
			'id' => 'resp_struct_456',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'msg_struct_2',
					'type' => 'message',
					'status' => 'completed',
					'content' => [
						(object) [
							'type' => 'output_text',
							'text' => '{"items":[1,1,1,1,2,2,3,3,3]}',
						],
					],
					'role' => 'assistant',
				],
			],
			'model' => 'gpt-5.1-2025-11-13',
		];

		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode($secondResponseBody))
		);

		$context[] = new ChatInput('Füge der Liste drei 3 em Ende hinzu.');

		$response2 = $chat->chat($context, responseFormat: $responseFormat);

		$this->assertIsObject($response2->firstChoice()->result);
		$this->assertSame([1, 1, 1, 1, 2, 2, 3, 3, 3], $response2->firstChoice()->result->items ?? []);

		$context = $response2->firstChoice()->enhancedContext;

		$this->assertCount(4, $context);
		$this->assertInstanceOf(ChatInput::class, $context[0]);
		$this->assertInstanceOf(ChatResponseChoice::class, $context[1]);
		$this->assertInstanceOf(ChatInput::class, $context[2]);
		$this->assertInstanceOf(ChatResponseChoice::class, $context[3]);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(2, $timeline);

		$body = $timeline[0]['request']->getBody();
		/** @var TRequestData $firstRequestData */
		$firstRequestData = JSON::parse((string) $body);

		$this->assertSame('json_schema', $firstRequestData->text->format->type ?? null);
		$this->assertSame('Response', $firstRequestData->text->format->name ?? null);
		$this->assertFalse($firstRequestData->text->format->strict ?? true);

		$schemaPayload = $firstRequestData->text->format->schema ?? [];
		$this->assertSame(['items'], $schemaPayload->required ?? null);
		$this->assertFalse($schemaPayload->additionalProperties ?? true);
		$this->assertSame('array', $schemaPayload->properties->items->type ?? null);
		$this->assertSame('integer', $schemaPayload->properties->items->items->type ?? null);

		/** @var TRequestData $secondRequestData */
		$secondRequestData = JSON::parse((string) $timeline[1]['request']->getBody());

		$this->assertSame('json_schema', $secondRequestData->text->format->type ?? null);
		$this->assertCount(3, $secondRequestData->input ?? []);
		$this->assertSame('assistant', $secondRequestData->input[1]->role);
		$this->assertSame('output_text', $secondRequestData->input[1]->content[0]->type);
		$this->assertSame('{"items":[1,1,1,1,2,2]}', $secondRequestData->input[1]->content[0]->text ?? null);
	}
}
