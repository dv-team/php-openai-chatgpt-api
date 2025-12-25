<?php

namespace DvTeam\ChatGPT\Response;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\MessageTypes\ToolCall;
use JsonSerializable;

/**
 * @phpstan-type TextOutput array{role: 'assistant', content: list<array{type: 'output_text', text: string}>}
 * @phpstan-type FunctionOutput array{type: 'function_call', call_id: string, name: string, arguments: string}
 */
class ChatResponseChoice implements JsonSerializable {
	/**
	 * @param object{toolCallMessage: ToolCall}[] $tools
	 */
	public function __construct(
		public readonly bool $isToolCall,
		public readonly null|string|object $result,
		public readonly ?string $textResult,
		public readonly ?object $objResult,
		public readonly array $tools,
	) {}

	/**
	 * @return array{result: null|string|object, tools: object[]}
	 */
	public function jsonSerialize(): array {
		return [
			'result' => $this->result,
			'tools' => $this->tools,
		];
	}
}
