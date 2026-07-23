<?php

namespace DvTeam\ChatGPT\Common;

use InvalidArgumentException;
use JsonSerializable;

final class PromptCacheOptions implements JsonSerializable {
	public function __construct(
		public readonly string $mode = 'implicit',
		public readonly string $ttl = '30m',
	) {
		if(!in_array($mode, ['implicit', 'explicit'], true)) {
			throw new InvalidArgumentException('Prompt cache mode must be implicit or explicit.');
		}

		if($ttl !== '30m') {
			throw new InvalidArgumentException('Prompt cache TTL must be 30m.');
		}
	}

	/**
	 * @return array{mode: string, ttl: string}
	 */
	public function jsonSerialize(): array {
		return [
			'mode' => $this->mode,
			'ttl' => $this->ttl,
		];
	}

	/**
	 * @param array<string, mixed>|object $data
	 */
	public static function fromArray(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		$mode = $data['mode'] ?? 'implicit';
		$ttl = $data['ttl'] ?? '30m';
		if(!is_string($mode) || !is_string($ttl)) {
			throw new InvalidArgumentException('Invalid prompt cache options.');
		}

		return new self($mode, $ttl);
	}
}
