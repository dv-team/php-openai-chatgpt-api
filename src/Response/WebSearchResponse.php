<?php

namespace DvTeam\ChatGPT\Response;

use RuntimeException;

class WebSearchResponse {
	public function __construct(
		public readonly object $output,
		public readonly object $structure,
	) {}

	public function tryGetFirstText(): ?string {
		return $this->output->content[0]->text ?? null;
	}

	public function getFirstText(): string {
		$text = $this->tryGetFirstText();
		if($text === null) {
			throw new RuntimeException('No text found in response');
		}

		return $text;
	}
}
