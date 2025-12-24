<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Exceptions\InvalidResponseException;
use DvTeam\ChatGPT\Http\HttpPostInterface;
use DvTeam\ChatGPT\OpenAIToken;
use DvTeam\ChatGPT\PredefinedModels\LLMCustomModel;
use DvTeam\ChatGPT\Response\WebSearchResponse;
use PHPUnit\Framework\TestCase;

final class ChatGPTWebSearchFunctionTest extends TestCase {
	private function makeChat(): ChatGPT {
		return new class extends ChatGPT {
			public function __construct() {
				parent::__construct(new OpenAIToken('test'), new class implements HttpPostInterface {
					public function post(string $url, array $data, array $headers, bool $disableTimeout = false): string {
						return '';
					}
				});
			}

			public function webSearch(string $query, ?array $userLocation = null, ?ChatModelName $model = null): WebSearchResponse {
				return new WebSearchResponse(
					id: 'resp_dummy',
					output: (object)['content' => [(object)['text' => 'result text']]],
					structure: (object)[],
					query: $query,
					userLocation: $userLocation,
					model: $model ? (string) $model : 'gpt-5.1',
					effort: null,
				);
			}
		};
	}

	public function testRequiresModelAndUserLocationWhenNoDefaults(): void {
		$chat = $this->makeChat();
		$fn = $chat->buildWebSearchFunction();
		$callable = $fn->getCallable();

		$this->expectException(InvalidResponseException::class);
		$callable((object)[
			'query' => 'hello world',
			// missing user_location and model -> should error
		]);
	}

	public function testRejectsReasoningModels(): void {
		$chat = $this->makeChat();
		$fn = $chat->buildWebSearchFunction();
		$callable = $fn->getCallable();

		$this->expectException(InvalidResponseException::class);
		$callable((object)[
			'query' => 'hello world',
			'user_location' => (object)['type' => 'approximate', 'country' => 'US'],
			'model' => 'gpt-5.1-reasoning',
		]);
	}

	public function testSuccessfulCallAddsMetadata(): void {
		$chat = $this->makeChat();
		$fn = $chat->buildWebSearchFunction();
		$callable = $fn->getCallable();

		$result = $callable((object)[
			'query' => 'hello world',
			'user_location' => (object)['type' => 'approximate', 'country' => 'US'],
			'model' => 'gpt-5.1',
		]);

		$this->assertSame('result text', $result['text']);
		$this->assertSame('hello world', $result['query']);
		$this->assertSame('gpt-5.1', $result['model']);
		$this->assertSame(['type' => 'approximate', 'country' => 'US'], $result['user_location']);
		$this->assertSame('resp_dummy', $result['response_id']);
	}

	public function testRequiredFieldsReflectDefaults(): void {
		$chat = $this->makeChat();

		// No defaults: all arguments required
		$fn = $chat->buildWebSearchFunction();
		$required = $fn->jsonSerialize()['parameters']->required ?? [];
		$this->assertContains('query', $required);
		$this->assertContains('user_location', $required);
		$this->assertContains('model', $required);

		// Defaults provided: only query required
		$defaultLoc = ['type' => 'approximate', 'country' => 'US'];
		$fnWithDefaults = $chat->buildWebSearchFunction($defaultLoc, new LLMCustomModel('gpt-5.1'));
		$required2 = $fnWithDefaults->jsonSerialize()['parameters']->required ?? [];
		$this->assertSame(['query'], $required2);
	}
}
