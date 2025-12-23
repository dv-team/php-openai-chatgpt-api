<?php

namespace DvTeam\ChatGPT\Response;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\MessageTypes\ToolCall;

class ChatFuncCallResult implements ChatMessage {
	/**
	 * @param string $id
	 * @param string $functionName
	 * @param object $arguments
	 * @param ToolCall $toolCallMessage The ready formatted tool call message to be added to the context of the chat.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $functionName,
		public readonly object $arguments,
		public readonly ToolCall $toolCallMessage,
	) {}

	public function addToContext(array $context): array {
		$context[] = $this;
		return $context;
	}

	/**
	 * @return list<array{type: 'function_call_output', call_id: string, output: string}>
	 */
	public function jsonSerialize(): array {
		return [[
			'type' => 'function_call_output',
			'call_id' => $this->id,
			'output' => JSON::stringify($this->arguments),
		]];
	}
}
