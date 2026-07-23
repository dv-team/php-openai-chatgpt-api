<?php

namespace DvTeam\ChatGPT\Response;

use JsonSerializable;

final class ResponseUsage implements JsonSerializable {
	public function __construct(
		public readonly int $inputTokens,
		public readonly int $cachedTokens,
		public readonly int $cacheWriteTokens,
		public readonly int $outputTokens,
		public readonly int $reasoningTokens,
		public readonly int $totalTokens,
	) {}

	/**
	 * @return array{
	 *     input_tokens: int,
	 *     input_tokens_details: array{cached_tokens: int, cache_write_tokens: int},
	 *     output_tokens: int,
	 *     output_tokens_details: array{reasoning_tokens: int},
	 *     total_tokens: int
	 * }
	 */
	public function jsonSerialize(): array {
		return [
			'input_tokens' => $this->inputTokens,
			'input_tokens_details' => [
				'cached_tokens' => $this->cachedTokens,
				'cache_write_tokens' => $this->cacheWriteTokens,
			],
			'output_tokens' => $this->outputTokens,
			'output_tokens_details' => [
				'reasoning_tokens' => $this->reasoningTokens,
			],
			'total_tokens' => $this->totalTokens,
		];
	}
}
