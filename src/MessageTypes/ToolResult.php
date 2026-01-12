<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ContextSerializable;
use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;
use InvalidArgumentException;

/**
 * Describes a tool call result for the message context that the LLM wanted to execute.
 */
class ToolResult implements ChatMessage, ContextSerializable {
	/**
	 * @param string $toolCallId
	 * @param null|scalar|array<string, mixed>|object $content
	 */
	public function __construct(
		public string $toolCallId,
		public mixed $content,
	) {}

	/**
	 * Maps a tool-result message to the Responses API input schema.
	 *
	 * @return list<object{type: 'function_call_output', call_id: string, output: string}>
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

		return [
			(object) [
				'type' => 'function_call_output',
				'call_id' => $this->toolCallId,
				'output' => $output,
			]
		];
	}

	/**
	 * @return array{type: 'tool_result', toolCallId: string, content: mixed}
	 */
	public function contextSerialize(): array {
		return [
			'type' => 'tool_result',
			'toolCallId' => $this->toolCallId,
			'content' => $this->content,
		];
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		$toolCallId = $data['toolCallId'] ?? $data['call_id'] ?? null;
		if(!is_string($toolCallId)) {
			throw new InvalidArgumentException('Invalid tool_result payload.');
		}

		return new self(
			toolCallId: $toolCallId,
			content: $data['content'] ?? null,
		);
	}

	public function __serialize(): array {
		return $this->contextSerialize();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function __unserialize(array $data): void {
		$obj = self::contextUnserialize($data);
		$this->toolCallId = $obj->toolCallId;
		$this->content = $obj->content;
	}
}
