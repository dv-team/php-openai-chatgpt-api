<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\PromptCacheOptions;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMLargeNoReasoning;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

/**
 * @phpstan-import-type TRequestData from ChatGPT
 */
final class ChatGPTPromptCacheTest extends TestCase {
	use TestTools;

	public function testPromptCacheConfigurationAndUsageAreExposed(): void {
		$mockClient = new MockClient();
		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode((object) [
				'id' => 'resp_cache_1',
				'status' => 'completed',
				'output' => [
					(object) [
						'type' => 'message',
						'role' => 'assistant',
						'content' => [
							(object) ['type' => 'output_text', 'text' => 'Cached answer'],
						],
					],
				],
				'usage' => (object) [
					'input_tokens' => 2048,
					'input_tokens_details' => (object) [
						'cached_tokens' => 1536,
						'cache_write_tokens' => 256,
					],
					'output_tokens' => 42,
					'output_tokens_details' => (object) [
						'reasoning_tokens' => 12,
					],
					'total_tokens' => 2090,
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

		$response = $chat->chat(
			context: [new ChatInput('Continue this cached conversation.')],
			model: new LLMLargeNoReasoning(),
			promptCacheKey: 'used-product:v1:session-123',
			promptCacheOptions: new PromptCacheOptions(),
		);

		$timeline = $mockClient->getTimeline();
		/** @var TRequestData $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());
		$this->assertSame('used-product:v1:session-123', $requestData->prompt_cache_key ?? null);
		$this->assertSame('implicit', $requestData->prompt_cache_options->mode ?? null);
		$this->assertSame('30m', $requestData->prompt_cache_options->ttl ?? null);

		$this->assertSame('resp_cache_1', $response->id);
		$this->assertNotNull($response->usage);
		$this->assertSame(1536, $response->usage->cachedTokens);
		$this->assertSame(256, $response->usage->cacheWriteTokens);
		$this->assertSame(12, $response->usage->reasoningTokens);

		$choice = $response->firstChoice();
		$this->assertSame('resp_cache_1', $choice->responseId);
		$this->assertNotNull($choice->usage);
		$this->assertSame(1536, $choice->usage->cachedTokens);
		$this->assertSame(256, $choice->usage->cacheWriteTokens);
	}
}
