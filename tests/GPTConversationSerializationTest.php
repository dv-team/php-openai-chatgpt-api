<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

final class GPTConversationSerializationTest extends TestCase {
	public function testConversationCanBeSerializedAndResumed(): void {
		$mockClient = new MockClient();

		$firstResponseBody = (object) [
			'id' => 'resp_step_1',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'fc_number',
					'type' => 'function_call',
					'status' => 'completed',
					'function' => (object) [
						'name' => 'get_number_by_letter',
						'arguments' => '{"letter":"A"}',
					],
					'call_id' => 'call_number_a',
				],
			],
			'model' => (string) new LLMMediumNoReasoning(),
		];

		$secondResponseBody = (object) [
			'id' => 'resp_step_2',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'msg_word',
					'type' => 'message',
					'status' => 'completed',
					'content' => [
						(object) [
							'type' => 'output_text',
							'text' => 'Sun',
						],
					],
					'role' => 'assistant',
				],
			],
			'model' => (string) new LLMMediumNoReasoning(),
		];

		$mockClient->addResponseWildcard(
			new Response(200, ['Content-Type' => 'application/json'], JSON::stringify($firstResponseBody))
		);
		$mockClient->addResponseWildcard(
			new Response(200, ['Content-Type' => 'application/json'], JSON::stringify($secondResponseBody))
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

		$numberFn = #[GPTCallableDescriptor(name: 'get_number_by_letter', description: 'Returns a number for a letter.')]
		function(#[GPTParameterDescriptor(['description' => 'Letter'])] string $letter): int {
			return match($letter) { 'A' => 1, default => 0 };
		};

		$wordFn = #[GPTCallableDescriptor(name: 'get_word_by_number', description: 'Returns a word for a number.')]
		function(#[GPTParameterDescriptor(['description' => 'Number'])] int $number): string {
			return match($number) { 1 => 'Sun', default => 'Unknown' };
		};

		$conversation = new GPTConversation(
			$chat,
			[new ChatInput('Give me the number for letter A using the tool.')],
			[$numberFn]
		);

		$first = $conversation->step();

		$this->assertNull($first->result);
		/** @var \DvTeam\ChatGPT\Response\ChatFuncCallResult[] $tools */
		$tools = $first->tools;
		$this->assertCount(1, $tools);
		$this->assertSame('get_number_by_letter', $tools[0]->functionName);

		$this->assertCount(3, $conversation->getContext());
		$this->assertInstanceOf(ToolResult::class, $conversation->getContext()[2]);
		$this->assertSame(1, $conversation->getContext()[2]->content);
		$this->assertSame('call_number_a', $conversation->getContext()[2]->toolCallId);

		$serialized = $conversation->serialize();
		$this->assertIsArray($serialized);
		$this->assertNotEmpty($serialized);

		// Rehydrate and continue with a different tool set.
		$conversation2 = GPTConversation::fromSerialized(
			$chat,
			$serialized,
			tools: [$wordFn]
		);

		$conversation2->addMessage(new ChatInput('Return the word for the previous number.'));

		$second = $conversation2->step();

		$this->assertSame('Sun', $second->result);
		$this->assertCount(5, $conversation2->getContext());
	}
}
