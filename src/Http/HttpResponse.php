<?php

namespace DvTeam\ChatGPT\Http;

final class HttpResponse {
	/**
	 * @param int $statusCode
	 * @param array<string, string[]> $headers
	 * @param string $body
	 */
	public function __construct(
		public readonly int $statusCode,
		public readonly array $headers,
		public readonly string $body,
	) {}
}

