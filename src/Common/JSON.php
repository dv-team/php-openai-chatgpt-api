<?php

namespace DvTeam\ChatGPT\Common;

/**
 * @internal
 */
class JSON {
	public static function stringify(mixed $data): string {
		return json_encode(value: $data, flags: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}

	public static function parse(string $data): mixed {
		return json_decode(json: $data, associative: false, flags: JSON_THROW_ON_ERROR);
	}
}
