<?php

namespace DvTeam\ChatGPT\Messages;

class ChatImageUrl implements ChatAttachment {
	public function __construct(public string $url) {}
}
