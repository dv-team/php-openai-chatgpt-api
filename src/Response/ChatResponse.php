<?php

namespace DvTeam\ChatGPT\Response;

class ChatResponse {
	/**
	 * @param ChatResponseChoice[] $choices
	 */
	public function __construct(
		public readonly array $choices,
	) {}

	public function firstChoice(): ChatResponseChoice {
		return $this->choices[0];
	}
}
