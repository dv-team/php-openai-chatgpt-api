<?php

namespace DvTeam\ChatGPT\Common;

use JsonSerializable;

interface ChatMessage extends JsonSerializable {
	/**
	 * @return object[]
	 */
	public function jsonSerialize(): array;
}
