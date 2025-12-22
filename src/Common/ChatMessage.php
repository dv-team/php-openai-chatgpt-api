<?php

namespace DvTeam\ChatGPT\Common;

use JsonSerializable;

interface ChatMessage extends JsonSerializable {
	/**
	 * @param ChatMessage[] $context The context to enhance
	 * @return ChatMessage[] The enhanced context
	 */
	public function addToContext(array $context): array;

	/**
	 * @return array{}[]
	 */
	public function jsonSerialize(): array;
}
