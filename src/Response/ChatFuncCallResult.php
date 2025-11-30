<?php

namespace DvTeam\ChatGPT\Response;

use DvTeam\ChatGPT\MessageTypes\ToolCall;

class ChatFuncCallResult {
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
}
