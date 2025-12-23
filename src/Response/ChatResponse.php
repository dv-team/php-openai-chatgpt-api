<?php

namespace DvTeam\ChatGPT\Response;

use DvTeam\ChatGPT\Common\ChatMessage;

class ChatResponse {
	/**
	 * @param ChatResponseChoice[] $choices
	 * @param ChatMessage[] $enhancedContext
	 */
	public function __construct(
		public readonly array $choices,
		public readonly array $enhancedContext = [],
	) {}

	public function firstChoice(): ChatResponseChoice {
		return $this->choices[0];
	}
}
