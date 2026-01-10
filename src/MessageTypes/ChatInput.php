<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Messages\ChatAttachment;
use DvTeam\ChatGPT\Messages\ChatImageUrl;
use RuntimeException;

class ChatInput implements ChatMessage {
	public static function mk(string $content, string $role = 'user', ?ChatAttachment $attachment = null): ChatInput {
		return new ChatInput(content: $content, role: $role, attachment: $attachment);
	}

	public function __construct(
		public string $content,
		public string $role = 'user',
		public ?ChatAttachment $attachment = null,
	) {}

	/**
	 * Maps the structure of this ChatInput (optionally with an image) to the Responses API input schema.
	 *
	 * @return list<array{role: string, content: list<array{type: 'input_text', text: string}|array{type: 'input_image', image_url: string}>}>
	 */
	public function jsonSerialize(): array {
		$content = [[
			'type' => 'input_text',
			'text' => $this->content,
		]];

		if($this->attachment instanceof ChatImageUrl) {
			$content[] = [
				'type' => 'input_image',
				'image_url' => $this->attachment->url,
			];
		} elseif($this->attachment !== null) {
			throw new RuntimeException('Invalid parameter');
		}

		return [[
			'role' => $this->role,
			'content' => $content,
		]];
	}
}
