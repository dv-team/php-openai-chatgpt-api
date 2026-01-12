<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ContextSerializable;
use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;
use InvalidArgumentException;

/**
 * Describes the output of a chat message.
 */
class ChatOutput implements ChatMessage, ContextSerializable {
	/**
	 * @param ToolCall[] $tools
	 */
	public function __construct(
		public readonly null|string|object $result,
		public readonly array $tools
	) {}

	public function jsonSerialize(): array {
		$content = [];
		$data = $this->result;

		if(is_null($data)) {
		} elseif(!is_string($data)) {
			$content[] = (object) [
				'type' => 'output_text',
				'text' => JSON::stringify($data),
			];
		} else {
			$content[] = (object) [
				'type' => 'output_text',
				'text' => $data,
			];
		}

		$output = [];

		if(count($content)) {
			$output[] = (object) [
				'type' => 'message',
				'role' => 'assistant',
				'content' => $content,
			];
		}

		foreach($this->tools as $tool) {
			$output[] = $tool->jsonSerialize();
		}

		return $output;
	}

	/**
	 * @return array{type: 'chat_output', result: object|string|null, tools: array<int, array<string, mixed>>}
	 */
	public function contextSerialize(): array {
		return [
			'type' => 'chat_output',
			'result' => $this->result,
			'tools' => array_map(
				static fn(ToolCall $tool): array => $tool->contextSerialize(),
				$this->tools
			),
		];
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		$result = $data['result'] ?? null;
		if(is_array($result)) {
			$parsed = JSON::parse(JSON::stringify($result));
			$result = is_object($parsed) ? $parsed : JSON::stringify($result);
		}

		if(!is_null($result) && !is_string($result) && !is_object($result)) {
			throw new InvalidArgumentException('Invalid chat_output result payload.');
		}

		$toolsRaw = $data['tools'] ?? [];
		if(is_object($toolsRaw)) {
			$toolsRaw = (array) $toolsRaw;
		}

		if(!is_array($toolsRaw)) {
			throw new InvalidArgumentException('Invalid chat_output tools payload.');
		}

		$tools = [];
		foreach($toolsRaw as $tool) {
			if(is_object($tool)) {
				$tool = (array) $tool;
			}

			if(is_array($tool)) {
				$type = $tool['type'] ?? null;
				$name = $tool['name'] ?? null;

				if($type === 'web_search_call' || (($type === 'tool_call' || $type === 'function') && $name === 'web_search')) {
					$tools[] = WebSearchCall::contextUnserialize($tool);
					continue;
				}
			}

			$tools[] = ToolCall::contextUnserialize($tool);
		}

		return new self(
			result: $result,
			tools: $tools,
		);
	}

	public function __serialize(): array {
		return $this->contextSerialize();
	}
}
