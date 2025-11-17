<?php

namespace DvTeam\ChatGPT\Http;

interface HttpPostInterface {
	/**
	 * @param string $url
	 * @param array<string, scalar|array<mixed, mixed>|object> $data
	 * @param array<string, string> $headers
	 * @return string
	 */
	public function post(string $url, array $data, array $headers): string;
}