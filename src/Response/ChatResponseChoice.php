<?php

namespace DvTeam\ChatGPT\Response;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\MessageTypes\ToolCall;

/**
 * @phpstan-type TextOutput array{role: 'assistant', content: list<array{type: 'output_text', text: string}>}
 * @phpstan-type FunctionOutput array{type: 'function_call', call_id: string, name: string, arguments: string}
 */
class ChatResponseChoice implements ChatMessage {
	/**
	 * @param object{toolCallMessage: ToolCall}[] $tools
	 * @param ChatMessage[] $enhancedContext
	 */
	public function __construct(
		public readonly null|string|object $result,
		public readonly ?string $textResult,
		public readonly ?object $objResult,
		public readonly array $tools,
		public readonly array $enhancedContext = [],
	) {}

	public function addToContext(array $context): array {
		$context[] = $this;
		return $context;
	}

	/**
	 * @return list<TextOutput|FunctionOutput>
	 */
	public function jsonSerialize(): array {
		$content = [];
		$data = $this->result;

		if(is_null($data)) {
		} elseif(!is_string($data)) {
			$content[] = [
				'type' => 'output_text',
				'text' => JSON::stringify($data),
			];
		} else {
			$content[] = [
				'type' => 'output_text',
				'text' => $data,
			];
		}

		$output = [];

		if(count($content)) {
			$output[] = [
				'role' => 'assistant',
				'content' => $content,
			];
		}

		foreach($this->tools as $tool) {
			// Each tool encodes to a function_call item for the Responses API input.
			foreach($tool->toolCallMessage->jsonSerialize() as $toolInput) {
				$output[] = $toolInput;
			}
		}

		return $output;
	}
}
