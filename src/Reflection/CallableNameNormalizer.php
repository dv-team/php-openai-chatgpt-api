<?php

namespace DvTeam\ChatGPT\Reflection;

final class CallableNameNormalizer {
	public static function normalize(string $name): string {
		if(str_contains($name, '::')) {
			$name = substr($name, strrpos($name, '::') + 2);
		}

		$name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);

		return strtolower((string) $name);
	}
}
