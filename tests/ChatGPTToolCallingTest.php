<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTNumberProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTStringProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\Response\ChatFuncCallResult;
use DvTeam\ChatGPT\Response\ChatResponseChoice;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

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

		$functions = new GPTFunctions(
			new GPTFunction(
				name: 'get_number_by_letter',
				description: 'Returns a number for a single letter.',
				properties: new GPTProperties(
					new GPTStringProperty(name: 'letter', description: 'The letter for which to return the number.', required: true)
				)
			)
		);

		$context = [new ChatInput('Find the number for A and C.')];

		$response = $chat->chat($context, functions: $functions);
		$choice = $response->firstChoice();

		$this->assertInstanceOf(ChatResponseChoice::class, $choice);
		$this->assertNull($choice->result);
		$this->assertCount(2, $choice->tools);

		$firstTool = $choice->tools[0];
		$secondTool = $choice->tools[1];

		$this->assertInstanceOf(ChatFuncCallResult::class, $firstTool);
		$this->assertSame('get_number_by_letter', $firstTool->functionName);
		$this->assertSame('A', $firstTool->arguments->letter ?? null);
		$this->assertSame('call_number_a', $firstTool->id);

		$this->assertInstanceOf(ChatFuncCallResult::class, $secondTool);
		$this->assertSame('C', $secondTool->arguments->letter ?? null);
		$this->assertSame('call_number_c', $secondTool->id);

		$this->assertCount(2, $response->enhancedContext);
		$this->assertInstanceOf(ChatInput::class, $response->enhancedContext[0]);
		$this->assertInstanceOf(ChatResponseChoice::class, $response->enhancedContext[1]);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(1, $timeline);

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

		$context = $response->enhancedContext;

		foreach($choice->tools as $tool) {
			$context[] = new ToolResult($tool->id, match($tool->arguments->letter) {
				'A' => 1,
				'C' => 3,
				default => null
			});
		}

		$context[] = new ChatInput('Return the words for these numbers using get_a_word_by_number.');

		$wordFunctions = new GPTFunctions(
			new GPTFunction(
				name: 'get_a_word_by_number',
				description: 'Returns a word for a single number.',
				properties: new GPTProperties(
					new GPTNumberProperty(name: 'number', description: 'The number.', required: true)
				)
			)
		);

		$response2 = $chat->chat($context, functions: $wordFunctions);

		$this->assertSame('Sun and Earth', $response2->firstChoice()->result);
		$this->assertCount(6, $response2->enhancedContext);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(2, $timeline);

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
