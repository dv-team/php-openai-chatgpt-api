<?php

namespace DvTeam\ChatGPT\Response;

/**
 * @template T of object
 */
class ChatResponse {
	/**
	 * @param ChatResponseChoice<T>[] $choices
	 */
	public function __construct(
		public readonly array $choices,
	) {}

	/**
	 * @return ChatResponseChoice<T>
	 */
	public function firstChoice(): ChatResponseChoice {
		return $this->choices[0];
	}
}
