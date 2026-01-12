<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ContextSerializable;
use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Messages\ChatAttachment;
use DvTeam\ChatGPT\Messages\ChatImageUrl;
use InvalidArgumentException;
use RuntimeException;
use Serializable;

/**
 * Describes a chat message that the LLM wants to send to the user.
 */
class ChatInput implements ChatMessage, Serializable, ContextSerializable {
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
	 * @return list<object{type: 'message', role: string, content: list<object{type: 'input_text', text: string}|object{type: 'input_image', image_url: string}>}>
	 */
	public function jsonSerialize(): array {
		$content = [
			(object) [
				'type' => 'input_text',
				'text' => $this->content,
			]
		];

		if($this->attachment instanceof ChatImageUrl) {
			$content[] = (object) [
				'type' => 'input_image',
				'image_url' => $this->attachment->url,
			];
		} elseif($this->attachment !== null) {
			throw new RuntimeException('Invalid parameter');
		}

		return [
			(object) [
				'type' => 'message',
				'role' => $this->role,
				'content' => $content,
			]
		];
	}

	public function contextSerialize(): array {
		$attachment = null;

		if($this->attachment instanceof ContextSerializable) {
			$attachment = $this->attachment->contextSerialize();
		} elseif($this->attachment !== null) {
			throw new RuntimeException(sprintf('Unsupported attachment type: %s', $this->attachment::class));
		}

		return [
			'type' => 'chat_input',
			'content' => $this->content,
			'role' => $this->role,
			'attachment' => $attachment,
		];
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		$content = $data['content'] ?? null;
		$role = $data['role'] ?? 'user';

		if(!is_string($content) || !is_string($role)) {
			throw new InvalidArgumentException('Invalid chat_input payload.');
		}

		$attachment = null;
		$rawAttachment = $data['attachment'] ?? null;
		if($rawAttachment !== null) {
			if(is_object($rawAttachment)) {
				$rawAttachment = (array) $rawAttachment;
			}

			if(is_array($rawAttachment) && ($rawAttachment['type'] ?? null) === 'image_url') {
				$attachment = ChatImageUrl::contextUnserialize($rawAttachment);
			} else {
				throw new InvalidArgumentException('Unsupported attachment type.');
			}
		}

		return new self(
			content: $content,
			role: $role,
			attachment: $attachment,
		);
	}

	public function serialize(): string {
		return serialize($this->__serialize());
	}

	public function unserialize(string $data): void {
		$decoded = unserialize($data, ['allowed_classes' => false]);
		if(!is_array($decoded)) {
			throw new InvalidArgumentException('Invalid serialized ChatInput payload.');
		}

		$this->__unserialize($decoded);
	}

	public function __serialize(): array {
		return $this->contextSerialize();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function __unserialize(array $data): void {
		$obj = self::contextUnserialize($data);

		$this->content = $obj->content;
		$this->role = $obj->role;
		$this->attachment = $obj->attachment;
	}
}
