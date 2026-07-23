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
	 * @param object[] $outputItems Raw Responses API output items for lossless replay.
	 */
	public function __construct(
		public readonly null|string|object $result,
		public readonly array $tools,
		public readonly array $outputItems = [],
	) {}

	public function jsonSerialize(): array {
		if(count($this->outputItems)) {
			return $this->outputItems;
		}

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
	 * @return array{type: 'chat_output', result: object|string|null, tools: array<int, array<string, mixed>>, output_items?: array<int, array<string, mixed>>}
	 */
	public function contextSerialize(): array {
		$outputItems = array_map(
			static function(object $item): array {
				/** @var array<string, mixed> $data */
				$data = json_decode(JSON::stringify($item), true, 512, JSON_THROW_ON_ERROR);
				return $data;
			},
			$this->outputItems,
		);

		$data = [
			'type' => 'chat_output',
			'result' => $this->result,
			'tools' => array_map(
				static fn(ToolCall $tool): array => $tool->contextSerialize(),
				$this->tools
			),
		];

		if(count($outputItems)) {
			$data['output_items'] = $outputItems;
		}

		return $data;
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

				if($type === 'web_search_call') {
					$tools[] = WebSearchCall::contextUnserialize($tool);
					continue;
				}
			}

			$tools[] = ToolCall::contextUnserialize($tool);
		}

		$outputItemsRaw = $data['output_items'] ?? [];
		if(is_object($outputItemsRaw)) {
			$outputItemsRaw = (array) $outputItemsRaw;
		}
		if(!is_array($outputItemsRaw)) {
			throw new InvalidArgumentException('Invalid chat_output output_items payload.');
		}

		$outputItems = [];
		foreach($outputItemsRaw as $item) {
			if(is_array($item)) {
				$item = JSON::parse(JSON::stringify($item));
			}
			if(!is_object($item)) {
				throw new InvalidArgumentException('Invalid chat_output output item payload.');
			}
			$outputItems[] = $item;
		}

		return new self(
			result: $result,
			tools: $tools,
			outputItems: $outputItems,
		);
	}

	public function __serialize(): array {
		return $this->contextSerialize();
	}
}
