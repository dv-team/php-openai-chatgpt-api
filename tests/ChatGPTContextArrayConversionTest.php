<?php

declare(strict_types = 1);


use DvTeam\ChatGPT\ChatGPT;
use DvTeam\ChatGPT\Common\TestTools;
use DvTeam\ChatGPT\Messages\ChatImageUrl;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ChatOutput;
use DvTeam\ChatGPT\MessageTypes\ToolCall;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type TRequestData from ChatGPT
 */
class ChatGPTContextArrayConversionTest extends TestCase {
	use TestTools;

	public function testAsArray(): void {
		$array = ChatGPT::contextAsArray([
			new ChatInput(content: 'Be happy', role: 'system'),
			new ChatInput(content: 'Say Something!', role: 'user'),
			new ChatOutput(result: 'Something!', tools: []),
			new ChatInput(content: 'Generate an Alt-Text for the attached Image!', role: 'user', attachment: new ChatImageUrl(url: 'xyz')),
			new ChatOutput(result: '... image description ...', tools: []),

			new ChatOutput(result: 'Tool-Call Request', tools: [
				new ToolCall(id: 'abc123', name: 'my_func', arguments: ['file_name' => 'test.txt'], type: 'function', role: 'assistant'),
			]),
			new ToolResult(toolCallId: 'abc123', content: 'File contents'),

			new ChatOutput(result: null, tools: [
				new WebSearchCall(
					id: 'web_1',
					query: 'What is the capital of France?',
					userLocation: ['type' => 'approximate', 'country' => 'DE'],
					model: 'standard',
					effort: 'medium',
				)
			]),
		]);

		$expected = [
			[
				'type' => 'chat_input',
				'content' => 'Be happy',
				'role' => 'system',
				'attachment' => null,
			],
			[
				'type' => 'chat_input',
				'content' => 'Say Something!',
				'role' => 'user',
				'attachment' => null,
			],
			[
				'type' => 'chat_output',
				'result' => 'Something!',
				'tools' => [],
			],
			[
				'type' => 'chat_input',
				'content' => 'Generate an Alt-Text for the attached Image!',
				'role' => 'user',
				'attachment' => [
					'type' => 'image_url',
					'url' => 'xyz'
				],
			],
			[
				'type' => 'chat_output',
				'result' => '... image description ...',
				'tools' => [],
			],
			[
				'type' => 'chat_output',
				'result' => 'Tool-Call Request',
				'tools' => [
					[
						'type' => 'tool_call',
						'id' => 'abc123',
						'name' => 'my_func',
						'arguments' => ['file_name' => 'test.txt'],
						'tool_type' => 'function',
						'role' => 'assistant',
					]
				],
			],
			[
				'type' => 'tool_result',
				'toolCallId' => 'abc123',
				'content' => 'File contents',
			],
			[
				'type' => 'chat_output',
				'result' => null,
				'tools' => [
					[
						'type' => 'web_search_call',
						'id' => 'web_1',
						'query' => 'What is the capital of France?',
						'user_location' => ['type' => 'approximate', 'country' => 'DE'],
						'model' => 'standard',
						'effort' => 'medium',
					]
				],
			],
		];

		self::assertEquals($expected, $array);

		$context = ChatGPT::contextFromArray($expected);
		self::assertEquals($expected, ChatGPT::contextAsArray($context));

		// Ensure stdClass payloads (e.g. from json_decode()) are accepted.
		$asObjects = json_decode(json_encode($expected, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray($asObjects);

		$context = ChatGPT::contextFromArray($asObjects);
		self::assertEquals($expected, ChatGPT::contextAsArray($context));
	}

	public function testFromArrayRejectsUnknownType(): void {
		$this->expectException(\RuntimeException::class);
		ChatGPT::contextFromArray([['type' => 'function', 'id' => 'x', 'name' => 'y', 'arguments' => []]]);
	}
}
