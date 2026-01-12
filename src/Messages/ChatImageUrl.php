<?php

namespace DvTeam\ChatGPT\Messages;

use DvTeam\ChatGPT\Common\ContextSerializable;
use InvalidArgumentException;

/**
 *
 */
class ChatImageUrl implements ChatAttachment, ContextSerializable {
	public function __construct(public string $url) {}

	/**
	 * @return array{object{type: string, image_url: string}}
	 */
	public function toInputContentParts(): array {
		return [
			(object) [
				'type' => 'input_image',
				'image_url' => $this->url,
			]
		];
	}

	/**
	 * @return array{type: 'image_url', url: string}
	 */
	public function contextSerialize(): array {
		return [
			'type' => 'image_url',
			'url' => $this->url,
		];
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		if(($data['type'] ?? null) !== 'image_url' || !is_string($data['url'] ?? null)) {
			throw new InvalidArgumentException('Invalid image_url attachment payload.');
		}

		return new self(url: $data['url']);
	}
}
