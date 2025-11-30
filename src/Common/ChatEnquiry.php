<?php

namespace DvTeam\ChatGPT\Common;

class ChatEnquiry {
	/**
	 * @param ChatMessage[] $inputs
	 * @param mixed[] $functions
	 * @param null|mixed[] $responseFormat
	 * @param null|float $temperature The temperature as described in the [here](https://community.openai.com/t/cheat-sheet-mastering-temperature-and-top-p-in-chatgpt-api/172683).
	 */
	public function __construct(
		public readonly array $inputs,
		public readonly ChatModelName $model,
		public readonly array $functions,
		public readonly ?array $responseFormat = [],
		public readonly ?int $maxTokens = null,
		public readonly ?float $temperature = null,
		public readonly ?float $topP = null,
	) {}
}
