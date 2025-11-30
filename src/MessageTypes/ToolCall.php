<?php

namespace DvTeam\ChatGPT\MessageTypes;

/**
 * Beschreibt einen Tool-Call für den Message-Context, den das LLM ausführen lassen wollte.
 */
class ToolCall {
	/**
	 * @param string $id An unique identifier to connect the tool call with the result.
	 * @param string $name The name of the tool (function).
	 * @param array<string, mixed>|object $arguments The arguments for the tool call.
	 * @param string $type The type of the tool (function).
	 * @param string $role The role of the user in the conversation.
	 */
	public function __construct(
		public string $id,
		public string $name,
		public array|object $arguments,
		public string $type = 'function',
		public string $role = 'assistant',
	) {}
}
