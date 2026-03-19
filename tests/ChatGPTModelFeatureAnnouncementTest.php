<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMCustomModel;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr18\Client as MockClient;

/**
 * @phpstan-import-type TRequestData from ChatGPT
 */
final class ChatGPTModelFeatureAnnouncementTest extends TestCase {
	use TestTools;

	public function testNoReasoningModelSendsSamplingAndTokenParams(): void {
		$mockClient = new MockClient();
		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode($this->buildBasicResponseBody()))
		);

		$chat = $this->buildChat($mockClient);
		$chat->chat(
			context: [new ChatInput('Hello')],
			model: new LLMMediumNoReasoning(),
			maxTokens: 333,
			temperature: 0.2,
			topP: 0.7,
		);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(1, $timeline);

		/** @var TRequestData $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());

		$this->assertSame(333, $requestData->max_output_tokens ?? null);
		$this->assertSame(0.2, $requestData->temperature ?? null);
		$this->assertSame(0.7, $requestData->top_p ?? null);
	}

	public function testReasoningModelOmitsUnsupportedSamplingParams(): void {
		$mockClient = new MockClient();
		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode($this->buildBasicResponseBody()))
		);

		$chat = $this->buildChat($mockClient);
		$chat->chat(
			context: [new ChatInput('Hello')],
			model: new LLMSmallReasoning(ReasoningEffort::Medium),
			maxTokens: 444,
			temperature: 0.5,
			topP: 0.4,
		);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(1, $timeline);

		/** @var TRequestData $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());

		$this->assertSame(444, $requestData->max_output_tokens ?? null);
		$this->assertObjectNotHasProperty('temperature', $requestData);
		$this->assertObjectNotHasProperty('top_p', $requestData);
	}

	public function testCustomReasoningModelOmitsUnsupportedSamplingParams(): void {
		$mockClient = new MockClient();
		$mockClient->addResponse(
			'POST',
			'https://api.openai.com/v1/responses',
			new Response(200, ['Content-Type' => 'application/json'], self::jsonEncode($this->buildBasicResponseBody()))
		);

		$chat = $this->buildChat($mockClient);
		$chat->chat(
			context: [new ChatInput('Hello')],
			model: new LLMCustomModel('gpt-5.4', effort: ReasoningEffort::High),
			maxTokens: 555,
			temperature: 0.9,
			topP: 0.1,
		);

		$timeline = $mockClient->getTimeline();
		$this->assertCount(1, $timeline);

		/** @var TRequestData $requestData */
		$requestData = JSON::parse((string) $timeline[0]['request']->getBody());

		$this->assertSame(555, $requestData->max_output_tokens ?? null);
		$this->assertObjectNotHasProperty('temperature', $requestData);
		$this->assertObjectNotHasProperty('top_p', $requestData);
	}

	private function buildChat(MockClient $mockClient): ChatGPT {
		$httpClient = Psr18HttpClient::create(
			client: $mockClient,
			requestFactory: new HttpFactory(),
			streamFactory: new HttpFactory()
		);

		return new ChatGPT(
			token: new OpenAIToken('test-token'),
			httpPostClient: $httpClient
		);
	}

	private function buildBasicResponseBody(): object {
		return (object) [
			'id' => 'resp_feature_1',
			'object' => 'response',
			'created_at' => time(),
			'status' => 'completed',
			'output' => [
				(object) [
					'id' => 'msg_feature_1',
					'type' => 'message',
					'status' => 'completed',
					'content' => [
						(object) [
							'type' => 'output_text',
							'text' => 'ok',
						],
					],
					'role' => 'assistant',
				],
			],
			'model' => 'gpt-5.4',
		];
	}
}
