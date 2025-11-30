<?php

namespace DvTeam\ChatGPT\Response;

use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;
use RuntimeException;

/**
 * @phpstan-type TUserLocation array{type: string, city?: string, region?: string, country?: string, timezone?: string}
 */
class WebSearchResponse {
	/**
	 * @param object $output
	 * @param object $structure
	 * @param string $query
	 * @param TUserLocation|null $userLocation
	 * @param string $model
	 * @param string|null $effort
	 */
	public function __construct(
		public readonly object $output,
		public readonly object $structure,
		public readonly string $query,
		public readonly ?array $userLocation,
		public readonly string $model,
		public readonly ?string $effort,
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

	/**
	 * Return all textual message chunks from the web search output, if present.
	 * @return string[]
	 */
	public function getTexts(): array {
		$texts = [];
		$items = $this->output->content ?? [];
		if (is_array($items)) {
			foreach ($items as $item) {
				if (isset($item->text) && is_string($item->text)) {
					$texts[] = $item->text;
				}
			}
		}
		return $texts;
	}

	/**
	 * Create a dedicated WebSearchCall message based on this response metadata.
	 */
	public function getWebSearchCall(?string $id = null): WebSearchCall {
		$id = $id ?? ('web_' . substr(sha1($this->query . microtime(true)), 0, 12));
		return new WebSearchCall(
			$id,
			$this->query,
			$this->userLocation,
			$this->model,
			$this->effort,
		);
	}

	/**
	 * Create a dedicated WebSearchResult message to pair with a WebSearchCall.
	 */
	public function getWebSearchResult(string $toolCallId): WebSearchResult {
		$extra = [
			'texts' => $this->getTexts(),
			'query' => $this->query,
			'model' => $this->model,
			'effort' => $this->effort,
			'user_location' => $this->userLocation,
		];
		return WebSearchResult::fromText($toolCallId, $this->getFirstText(), $extra);
	}
}
