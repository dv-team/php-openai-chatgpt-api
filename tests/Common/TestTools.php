<?php

namespace DvTeam\ChatGPT\Common;

trait TestTools {
	public static function jsonEncode(mixed $input): string {
		return (string) json_encode($input, JSON_THROW_ON_ERROR);
	}
}
