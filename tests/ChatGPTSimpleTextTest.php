<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

require_once __DIR__ . '/../vendor/autoload.php';

class ChatGPTSimpleTextTest extends TestCase {
	use TestTools;

	public function testChatReturnsParsedMessageAndSendsExpectedRequest(): void {
		$responseBody = (object) [
			'id' => 'resp_123',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'msg_123',
					'type' => 'message',
					'status' => 'completed',
					'content' => [
						(object) [
							'type' => 'output_text',
							'text' => 'The capital of France is Paris.',
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
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode($responseBody))
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

		$conversation = new GPTConversation($chat, [new ChatInput('What is the capital of France?')]);

		$firstChoice = $conversation->step();

		$this->assertSame('The capital of France is Paris.', $firstChoice->result);

		$context = $conversation->getContext();

		$this->assertCount(2, $context);
		$this->assertInstanceOf(ChatInput::class, $context[0]);
		$this->assertInstanceOf(\DvTeam\ChatGPT\MessageTypes\ChatOutput::class, $context[1]);

		$responseBody = (object) [
			'id' => 'resp_456',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'msg_456',
					'type' => 'message',
					'status' => 'completed',
					'content' => [
						(object) [
							'type' => 'output_text',
							'text' => 'Paris has approximately 2.1 to 2.2 million inhabitants.',
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
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode($responseBody))
		);

		$conversation->addMessage(new ChatInput('How many inhabitants does this city have?'));

		$secondChoice = $conversation->step();

		$this->assertSame('Paris has approximately 2.1 to 2.2 million inhabitants.', $secondChoice->result);

		$context = $conversation->getContext();

		$this->assertCount(4, $context);

		$this->assertInstanceOf(ChatInput::class, $context[0]);
		$this->assertInstanceOf(\DvTeam\ChatGPT\MessageTypes\ChatOutput::class, $context[1]);
		$this->assertInstanceOf(ChatInput::class, $context[2]);
		$this->assertInstanceOf(\DvTeam\ChatGPT\MessageTypes\ChatOutput::class, $context[3]);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(2, $timeline);

		$request = $timeline[0]['request'];

		$this->assertSame('https://api.openai.com/v1/responses', (string) $request->getUri());
		$this->assertSame('POST', $request->getMethod());

		$this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
		$this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
		$this->assertSame('application/json; charset=utf-8', $request->getHeaderLine('accept'));
	}
}
