<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;

/**
 * Beschreibt einen Tool-Call für den Message-Context, den das LLM ausführen lassen wollte.
 */
class ToolCall implements ChatMessage {
	/**
	 * @param string $id A unique identifier to connect the tool call with the result.
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

	public function addToContext(array $context): array {
		$context[] = $this;
		return $context;
	}

	/**
	 * Maps an assistant tool-call message to the Responses API input schema.
	 *
	 * @return list<array{type: 'function_call', call_id: string, name: string, arguments: string}>
	 */
	public function jsonSerialize(): array {
		return [[
			'type' => 'function_call',
			'call_id' => $this->id,
			'name' => $this->name,
			'arguments' => JSON::stringify($this->arguments),
		]];
	}
}
