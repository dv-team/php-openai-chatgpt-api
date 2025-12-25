<?php

namespace DvTeam\ChatGPT\Common;

use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;

class CallableTools {
	#[GPTCallableDescriptor(name: null, description: 'Pick a word by index.')]
	public static function pickWord(
		#[GPTParameterDescriptor(['description' => 'The index of the word to pick.'])]
		int $index,
	): string {
		$words = ['apple', 'banana', 'cherry'];

		return $words[$index] ?? 'unknown';
	}
}
