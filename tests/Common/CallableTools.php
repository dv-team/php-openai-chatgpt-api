<?php

namespace DvTeam\ChatGPT\Common;

use DvTeam\ChatGPT\Attributes\GPTMethod;
use DvTeam\ChatGPT\Attributes\GPTParameter;

class CallableTools {
	#[GPTMethod('Pick a word by index.')]
	public static function pickWord(
		#[GPTParameter('The numeric index of the word to return.')]
		int $index,
	): string {
		$words = ['apple', 'banana', 'cherry'];

		return $words[$index] ?? 'unknown';
	}
}
