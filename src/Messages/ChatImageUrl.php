<?php

namespace DvTeam\ChatGPT\Messages;

class ChatImageUrl implements ChatAttachment {
	public function __construct(public string $url) {}

	public function toInputContentParts(): array {
		return [[
			'type' => 'input_image',
			'image_url' => $this->url,
		]];
	}
}
