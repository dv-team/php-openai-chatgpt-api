<?php

namespace DvTeam\ChatGPT\Common;

/**
 * @phpstan-type TFunction array{
 *     name: string,
 *     description?: string,
 *     parameters: array<string, mixed>
 * }
 */
class ChatEnquiry {
	/**
	 * @param ChatMessage[] $inputs
	 * @param TFunction[] $functions
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
