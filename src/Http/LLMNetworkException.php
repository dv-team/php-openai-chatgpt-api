<?php

namespace DvTeam\ChatGPT\Http;

use JsonException;
use Throwable;

class LLMNetworkException extends LLMException {
	/**
	 * @param array<string, string[]> $headers
	 */
	public function __construct(public string $contents, public array $headers, int $statusCode, Throwable $previous = null) {
		$message = '';
		try {
			/** @var object{error?: object{message?: string}} $data */
			$data = json_decode($contents, associative: false, depth: 10, flags: JSON_THROW_ON_ERROR);
			$message = $data->error->message ?? '';
		} catch (JsonException) {
		}
		parent::__construct($message, $statusCode, $previous);
	}
}
