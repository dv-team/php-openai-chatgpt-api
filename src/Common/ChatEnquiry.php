<?php

namespace DvTeam\ChatGPT\Common;

use DvTeam\ChatGPT\Functions\GPTFunction;

/**
 * @phpstan-import-type TFunction from GPTFunction
 */
class ChatEnquiry {
	/**
	 * @param ChatMessage[] $context
	 * @param TFunction[] $functions
	 * @param null|mixed[] $responseFormat
	 * @param null|float $temperature The temperature as described in the [here](https://community.openai.com/t/cheat-sheet-mastering-temperature-and-top-p-in-chatgpt-api/172683).
	 */
	public function __construct(
		public readonly array $context,
		public readonly ChatModelName $model,
		public readonly array $functions,
		public readonly ?array $responseFormat = [],
		public readonly ?int $maxTokens = null,
		public readonly ?float $temperature = null,
		public readonly ?float $topP = null,
	) {}
}
