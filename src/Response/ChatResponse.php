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
		public readonly ?string $id = null,
		public readonly ?ResponseUsage $usage = null,
	) {}

	/**
	 * @return ChatResponseChoice<T>
	 */
	public function firstChoice(): ChatResponseChoice {
		return $this->choices[0];
	}
}
