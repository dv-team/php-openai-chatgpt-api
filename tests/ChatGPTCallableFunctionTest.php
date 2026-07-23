<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
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

		$conversation = new GPTConversation(
			$chat,
			[new ChatInput('Please pick a word for index 2.')],
			[[Common\CallableTools::class, 'pickWord']]
		);

		$response = $conversation->step();

		$this->assertNull($response->result);
		$this->assertCount(1, $response->tools);

		// Tool result should have been injected automatically into the conversation context.
		$this->assertCount(3, $conversation->getContext());
		$this->assertInstanceOf(ToolResult::class, $conversation->getContext()[2]);
		$this->assertSame('call_pick_word', $conversation->getContext()[2]->toolCallId);
		$this->assertSame('cherry', $conversation->getContext()[2]->content);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(1, $timeline);

		/** @var object{tools?: array<int, object{name?: string, description?: string, parameters?: object}>} $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());
		$this->assertSame('pick_word', $requestData->tools[0]->name ?? null);
		$this->assertSame('Pick a word by index.', $requestData->tools[0]->description ?? null);
		$this->assertSame('integer', $requestData->tools[0]->parameters->properties->index->type ?? null);
	}

	public function testNormalizedCallableArgumentNamesAreInvokedAutomatically(): void {
		$mockClient = new MockClient();
		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(
				200,
				['Content-Type' => 'application/json'],
				self::jsonEncode((object) [
					'id' => 'resp_callable_snake_case',
					'status' => 'completed',
					'output' => [
						(object) [
							'type' => 'function_call',
							'name' => 'submit_product_data',
							'arguments' => '{"product_json":"{\\"manufacturer\\":\\"Canon\\"}"}',
							'call_id' => 'call_submit_product_data',
						],
					],
				])
			)
		);

		$conversation = new GPTConversation(
			new ChatGPT(
				token: new OpenAIToken('test-token'),
				httpPostClient: Psr18HttpClient::create(
					client: $mockClient,
					requestFactory: new HttpFactory(),
					streamFactory: new HttpFactory(),
				),
			),
			[new ChatInput('Submit the product data.')],
			[[Common\CallableTools::class, 'submitProductData']],
		);

		$conversation->step();

		$this->assertCount(3, $conversation->getContext());
		$this->assertInstanceOf(ToolResult::class, $conversation->getContext()[2]);
		$this->assertSame('{"manufacturer":"Canon"}', $conversation->getContext()[2]->content);

		$timeline = $mockClient->getTimeline();
		/** @var object{tools: array<int, object{parameters: object{properties: object, required?: string[]}}>} $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());
		$this->assertSame(
			'string',
			$requestData->tools[0]->parameters->properties->product_json->type ?? null
		);
		$this->assertSame(
			['product_json'],
			$requestData->tools[0]->parameters->required ?? null
		);
	}

	public function testRunUntilResponseContinuesThroughToolsWithABound(): void {
		$mockClient = new MockClient();
		$mockClient->addResponseWildcard(new Response(
			200,
			['Content-Type' => 'application/json'],
			self::jsonEncode((object) [
				'id' => 'resp_callable_tool',
				'status' => 'completed',
				'output' => [
					(object) [
						'id' => 'reasoning_callable_tool',
						'type' => 'reasoning',
						'summary' => [],
					],
					(object) [
						'type' => 'function_call',
						'name' => 'pick_word',
						'arguments' => '{"index":1}',
						'call_id' => 'call_pick_word',
					],
				],
			])
		));
		$mockClient->addResponseWildcard(new Response(
			200,
			['Content-Type' => 'application/json'],
			self::jsonEncode((object) [
				'id' => 'resp_callable_answer',
				'status' => 'completed',
				'output' => [
					(object) [
						'type' => 'message',
						'status' => 'completed',
						'role' => 'assistant',
						'content' => [
							(object) ['type' => 'output_text', 'text' => 'The word is banana.'],
						],
					],
				],
			])
		));

		$chat = new ChatGPT(
			token: new OpenAIToken('test-token'),
			httpPostClient: Psr18HttpClient::create(
				client: $mockClient,
				requestFactory: new HttpFactory(),
				streamFactory: new HttpFactory(),
			),
		);
		$conversation = new GPTConversation(
			$chat,
			[new ChatInput('Pick word 1.')],
			[[Common\CallableTools::class, 'pickWord']],
		);

		$calledTools = [];
		$response = $conversation->runUntilResponse(
			maxSteps: 2,
			onToolCall: static function(\DvTeam\ChatGPT\MessageTypes\ToolCall $tool, int $step) use (&$calledTools): void {
				$calledTools[] = [$tool->name, $step];
			},
		);

		$this->assertSame('The word is banana.', $response->textResult);
		$this->assertSame([['pick_word', 1]], $calledTools);
		$this->assertCount(4, $conversation->getContext());
		$timeline = $mockClient->getTimeline();
		$this->assertCount(2, $timeline);

		/** @var object{input: object[]} $request */
		$request = JSON::parse((string) $timeline[1]['request']->getBody());
		$this->assertSame('reasoning', $request->input[1]->type ?? null);
		$this->assertSame('reasoning_callable_tool', $request->input[1]->id ?? null);
		$this->assertSame('function_call', $request->input[2]->type ?? null);
		$this->assertSame('function_call_output', $request->input[3]->type ?? null);
	}

	public function testRunUntilResponseStopsAtConfiguredBound(): void {
		$mockClient = new MockClient();
		$mockClient->addResponseWildcard(new Response(
			200,
			['Content-Type' => 'application/json'],
			self::jsonEncode((object) [
				'id' => 'resp_callable_tool_only',
				'status' => 'completed',
				'output' => [
					(object) [
						'type' => 'function_call',
						'name' => 'pick_word',
						'arguments' => '{"index":0}',
						'call_id' => 'call_pick_word',
					],
				],
			])
		));

		$conversation = new GPTConversation(
			new ChatGPT(
				token: new OpenAIToken('test-token'),
				httpPostClient: Psr18HttpClient::create(
					client: $mockClient,
					requestFactory: new HttpFactory(),
					streamFactory: new HttpFactory(),
				),
			),
			[new ChatInput('Keep calling tools.')],
			[[Common\CallableTools::class, 'pickWord']],
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('within 1 API steps');

		$conversation->runUntilResponse(maxSteps: 1);
	}
}
