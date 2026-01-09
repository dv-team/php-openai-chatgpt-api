<?php

namespace DvTeam\ChatGPT\Common;

use JsonSerializable;

interface ChatMessage extends JsonSerializable {
	/**
	 * @return list<array<string, mixed>>
	 */
	public function jsonSerialize(): array;
}
