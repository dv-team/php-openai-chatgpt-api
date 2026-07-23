<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\CallableTools;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\PromptCacheOptions;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\Messages\ChatImageUrl;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ChatOutput;
use DvTeam\ChatGPT\MessageTypes\ToolCall;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\PredefinedModels\LLMLargeReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

/**
 * @phpstan-import-type TRequestData from ChatGPT
 */
final class GPTConversationSessionTest extends TestCase {
	use TestTools;

	public function testCompleteSessionCanBeRestoredWithACurrentToolSet(): void {
		$mockClient = new MockClient();
		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode((object) [
				'id' => 'resp_session_1',
				'status' => 'completed',
				'output' => [
					(object) [
						'type' => 'message',
						'role' => 'assistant',
						'content' => [
							(object) ['type' => 'output_text', 'text' => '{"status":"ok"}'],
						],
					],
				],
			]))
		);

		$chat = new ChatGPT(
			token: new OpenAIToken('test-token'),
			httpPostClient: Psr18HttpClient::create(
				client: $mockClient,
				requestFactory: new HttpFactory(),
				streamFactory: new HttpFactory(),
			),
		);

		$conversation = new GPTConversation(
			chat: $chat,
				context: [
					new ChatInput(
						content: 'Inspect this product image.',
						attachment: new ChatImageUrl('https://example.com/product.jpg'),
					),
					new ChatOutput(
						result: null,
						tools: [new ToolCall('call_retired', 'retired_tool', ['product_id' => 42])],
					),
					new ToolResult('call_retired', ['status' => 'already handled']),
				],
			callableTools: [[CallableTools::class, 'pickWord']],
			responseFormat: new JsonSchemaResponseFormat([
				'type' => 'object',
				'properties' => [
					'status' => ['type' => 'string'],
				],
				'required' => ['status'],
				'additionalProperties' => false,
			], strict: true),
			model: new LLMLargeReasoning(ReasoningEffort::Medium),
			maxTokens: 321,
			temperature: 0.2,
			topP: 0.7,
			promptCacheKey: 'used-product:v1:session-123',
			promptCacheOptions: new PromptCacheOptions(),
		);

		$json = $conversation->toJson();
		$this->assertStringNotContainsString('pick_word', $json);

		$restored = GPTConversation::fromJson(
			chat: $chat,
			json: $json,
			tools: [[CallableTools::class, 'submitProductData']],
		);

		$this->assertSame($conversation->toArray(), $restored->toArray());

		$choice = $restored->step();
		$this->assertSame('ok', $choice->objResult->status ?? null);

		$timeline = $mockClient->getTimeline();
		/** @var TRequestData $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());
		$this->assertSame('gpt-5.6-sol', $requestData->model);
		$this->assertSame('medium', $requestData->reasoning->effort ?? null);
		$this->assertSame(321, $requestData->max_output_tokens ?? null);
		$this->assertObjectNotHasProperty('temperature', $requestData);
		$this->assertObjectNotHasProperty('top_p', $requestData);
		$this->assertSame('used-product:v1:session-123', $requestData->prompt_cache_key ?? null);
		$this->assertSame('implicit', $requestData->prompt_cache_options->mode ?? null);
		$this->assertSame('submit_product_data', $requestData->tools[0]->name ?? null);
		$this->assertCount(1, $requestData->tools ?? []);
	}
}
