<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\Response\ChatFuncCallResult;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

/**
 * @phpstan-import-type TRequestData from ChatGPT
 */
class ChatGPTToolCallingTest extends TestCase {
	use TestTools;

	public function testToolCallsAreMappedAndFedBack(): void {
		$this->expectOutputRegex('/.*/');

		$firstResponseBody = (object) [
			'id' => 'resp_tool_1',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'fc_tool_a',
					'type' => 'function_call',
					'status' => 'completed',
					'function' => (object) [
						'name' => 'get_number_by_letter',
						'arguments' => '{"letter":"A"}',
					],
					'call_id' => 'call_number_a',
				],
				(object) [
					'id' => 'fc_tool_c',
					'type' => 'function_call',
					'status' => 'completed',
					'function' => (object) [
						'name' => 'get_number_by_letter',
						'arguments' => '{"letter":"C"}',
					],
					'call_id' => 'call_number_c',
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

		$conversation = new GPTConversation(
			$chat,
			[new ChatInput('Find the number for A and C.')],
			[
				#[GPTCallableDescriptor(name: 'get_number_by_letter', description: 'Returns a number for a single letter.')]
				function(#[GPTParameterDescriptor(['description' => 'The letter for which to return the number.'])] string $letter): ?int {
					return match($letter) { 'A' => 1, 'B' => 2, 'C' => 3, default => null };
				}
			]
		);

		$choice = $conversation->step();

		$this->assertNull($choice->result);
		$this->assertCount(2, $choice->tools);

		/** @var ChatFuncCallResult $firstTool */
		$firstTool = $choice->tools[0];
		/** @var ChatFuncCallResult $secondTool */
		$secondTool = $choice->tools[1];

		$this->assertInstanceOf(ChatFuncCallResult::class, $firstTool);
		$this->assertSame('get_number_by_letter', $firstTool->functionName);
		$this->assertSame('A', $firstTool->arguments->letter ?? null);
		$this->assertSame('call_number_a', $firstTool->id);

		$this->assertInstanceOf(ChatFuncCallResult::class, $secondTool);
		$this->assertSame('C', $secondTool->arguments->letter ?? null);
		$this->assertSame('call_number_c', $secondTool->id);

		$this->assertCount(4, $conversation->getContext());
		$this->assertInstanceOf(ChatInput::class, $conversation->getContext()[0]);
		$this->assertInstanceOf(\DvTeam\ChatGPT\MessageTypes\ChatOutput::class, $conversation->getContext()[1]);
		$this->assertInstanceOf(ToolResult::class, $conversation->getContext()[2]);
		$this->assertInstanceOf(ToolResult::class, $conversation->getContext()[3]);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(1, $timeline);

		/** @var TRequestData $firstRequestData */
		$firstRequestData = JSON::parse((string) $timeline[0]['request']->getBody());

		$this->assertSame('Find the number for A and C.', $firstRequestData->input[0]->content[0]->text ?? null);
		$this->assertSame('function', $firstRequestData->tools[0]->type ?? null);
		$this->assertSame('get_number_by_letter', $firstRequestData->tools[0]->name ?? null);
		$this->assertSame('auto', $firstRequestData->tool_choice ?? null);

		$secondResponseBody = (object) [
			'id' => 'resp_tool_2',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'msg_tool_words',
					'type' => 'message',
					'status' => 'completed',
					'content' => [
						(object) [
							'type' => 'output_text',
							'text' => 'Sun and Earth',
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

		$conversation->addMessage(new ChatInput('Return the words for these numbers using get_a_word_by_number.'));

		$conversation->setTools([
			#[GPTCallableDescriptor(name: 'get_a_word_by_number', description: 'Returns a word for a single number.')]
			function(#[GPTParameterDescriptor(['description' => 'The number.'])] int $number): ?string {
				return match($number) { 1 => 'Sun', 2 => 'Moon', 3 => 'Earth', default => null };
			}
		]);

		$response2 = $conversation->step();

		$this->assertSame('Sun and Earth', $response2->result);
		$this->assertCount(6, $conversation->getContext());

		$timeline = $mockClient->getTimeline();
		$this->assertCount(2, $timeline);

		/** @var TRequestData $secondRequestData */
		$secondRequestData = JSON::parse((string) $timeline[1]['request']->getBody());

		$this->assertCount(6, $secondRequestData->input ?? []);
		$this->assertSame('function_call', $secondRequestData->input[1]->type ?? null);
		$this->assertSame('call_number_a', $secondRequestData->input[1]->call_id ?? null);
		$this->assertSame('function_call', $secondRequestData->input[2]->type ?? null);
		$this->assertSame('call_number_c', $secondRequestData->input[2]->call_id ?? null);
		$this->assertSame('function_call_output', $secondRequestData->input[3]->type ?? null);
		$this->assertSame('1', $secondRequestData->input[3]->output ?? null);
		$this->assertSame('function_call_output', $secondRequestData->input[4]->type ?? null);
		$this->assertSame('3', $secondRequestData->input[4]->output ?? null);
		$this->assertSame('Return the words for these numbers using get_a_word_by_number.', $secondRequestData->input[5]->content[0]->text ?? null);

		$this->assertSame('get_a_word_by_number', $secondRequestData->tools[0]->name ?? null);
		$this->assertSame('auto', $secondRequestData->tool_choice ?? null);
	}
}
