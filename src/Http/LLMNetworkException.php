<?php

namespace DvTeam\ChatGPT\Http;

use Throwable;

class LLMNetworkException extends LLMException {
	/**
	 * @param array<string, string[]> $headers
	 */
	public function __construct(public string $contents, public array $headers, int $statusCode, Throwable $previous = null) {
		parent::__construct('', $statusCode, $previous);
	}
}
