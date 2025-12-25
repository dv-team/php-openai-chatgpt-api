<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\JSON;

class ChatOutput implements ChatMessage {
	/**
	 * @param object{toolCallMessage: ToolCall}[] $tools
	 */
	public function __construct(
		public readonly null|string|object $result,
		public readonly array $tools
	) {}

	public function addToContext(array $context): array {
		$context[] = $this;
		return $context;
	}

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
