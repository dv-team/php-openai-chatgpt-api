<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\Reflection\GPTCallableFunctionFactory;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

class ChatGPTCallableFunctionTest extends TestCase {
	use TestTools;

	public function testCallableFunctionsAreDescribedAndInvokedAutomatically(): void {
		$this->expectOutputRegex('/.*/');

		$mockClient = new MockClient();

		$firstResponseBody = (object) [
			'id' => 'resp_callable_1',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'fc_word',
					'type' => 'function_call',
					'status' => 'completed',
					'function' => (object) [
						'name' => 'pick_word',
						'arguments' => '{"index":2}',
					],
					'call_id' => 'call_pick_word',
				],
			],
			'model' => 'gpt-5.1-2025-11-13',
		];

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

		$callableFunction = GPTCallableFunctionFactory::fromCallable([Common\CallableTools::class, 'pickWord'], 'pick_word');

		$functions = new GPTFunctions($callableFunction);

		$context = [new ChatInput('Please pick a word for index 2.')];

		$response = $chat->chat($context, functions: $functions);

		$this->assertNull($response->firstChoice()->result);
		$this->assertCount(1, $response->firstChoice()->tools);

		// Tool result should have been injected automatically into the enhanced context.
		$this->assertCount(3, $response->firstChoice()->enhancedContext);
		$this->assertInstanceOf(ToolResult::class, $response->firstChoice()->enhancedContext[2]);
		$this->assertSame('call_pick_word', $response->firstChoice()->enhancedContext[2]->toolCallId);
		$this->assertSame('cherry', $response->firstChoice()->enhancedContext[2]->content);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(1, $timeline);

		/** @var object{tools?: array<int, object{name?: string, description?: string, parameters?: object}>} $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());
		$this->assertSame('pick_word', $requestData->tools[0]->name ?? null);
		$this->assertSame('Pick a word by index.', $requestData->tools[0]->description ?? null);
		$this->assertSame('integer', $requestData->tools[0]->parameters->properties->index->type ?? null);
	}
}
