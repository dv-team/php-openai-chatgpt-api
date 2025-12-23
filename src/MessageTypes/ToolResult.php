<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;

/**
 * Beschreibt ein Tool-Call-Result für den Message-Context, den das LLM ausführen lassen wollte.
 */
class ToolResult implements ChatMessage {
	/**
	 * @param string $toolCallId
	 * @param null|scalar|array<string, mixed>|object $content
	 * @param string $role
	 */
	public function __construct(
		public string $toolCallId,
		public mixed $content,
		public string $role = 'tool',
	) {}

	public function addToContext(array $context): array {
		$context[] = $this;
		return $context;
	}

	/**
	 * Maps a tool-result message to the Responses API input schema.
	 *
	 * @return list<array{type: 'function_call_output', call_id: string, output: string}>
	 */
	public function jsonSerialize(): array {
		$output = $this->content;
		if(is_array($output) || is_object($output)) {
			$output = JSON::stringify($output);
		} elseif($output === null) {
			$output = '';
		} elseif(!is_string($output)) {
			$output = (string) $output;
		}

		return [[
			'type' => 'function_call_output',
			'call_id' => $this->toolCallId,
			'output' => $output,
		]];
	}
}
