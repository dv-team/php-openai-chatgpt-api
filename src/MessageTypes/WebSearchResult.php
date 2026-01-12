<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ContextSerializable;
use DvTeam\ChatGPT\Common\ChatMessage;
use InvalidArgumentException;

/**
 * Special helper class for the result of a web search in the chat context.
 *
 * This class extends ToolResult, sets the name 'web_search' by default,
 * and allows simple construction from text/texts.
 */
class WebSearchResult implements ChatMessage, ContextSerializable {
	/**
	 * @param string $id ID des zugehörigen WebSearchCall
	 * @param string|array<string, mixed> $content
	 */
	public function __construct(
		public string $id,
		public string|array $content
	) {}

	/**
	 * Komfort: Einfaches Ergebnis mit einem Text.
	 *
	 * @param string $toolCallId
	 * @param string $text
	 * @param array<string, mixed> $extra
	 * @return WebSearchResult
	 */
	public static function fromText(string $toolCallId, string $text, array $extra = []): self {
		$content = array_merge(['text' => $text], $extra);
		return new self($toolCallId, $content);
	}

	/**
	 * Komfort: Ergebnis mit mehreren Text-Snippets.
	 *
	 * @param string $toolCallId
	 * @param string[] $texts
	 * @param array<string, mixed> $extra
	 * @return WebSearchResult
	 */
	public static function fromTexts(string $toolCallId, array $texts, array $extra = []): self {
		$content = array_merge(['texts' => array_values($texts)], $extra);
		return new self($toolCallId, $content);
	}

	/**
	 * @return list<object{type: 'tool_result', tool_id: string, content: mixed}>
	 */
	public function jsonSerialize(): array {
		return [
			(object) [
				'type' => 'tool_result',
				'tool_id' => $this->id,
				'content' => $this->content,
			]
		];
	}

	/**
	 * @return array{type: 'web_search_result', id: string, content: string|array<string, mixed>}
	 */
	public function contextSerialize(): array {
		return [
			'type' => 'web_search_result',
			'id' => $this->id,
			'content' => $this->content,
		];
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		$id = $data['id'] ?? $data['tool_id'] ?? null;
		$content = $data['content'] ?? null;

		if(!is_string($id) || (!is_string($content) && !is_array($content))) {
			throw new InvalidArgumentException('Invalid web_search_result payload.');
		}

		return new self(
			id: $id,
			content: $content,
		);
	}

	public function __serialize(): array {
		return $this->contextSerialize();
	}
}
