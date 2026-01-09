<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ChatMessage;

/**
 * Spezielle Hilfsklasse für das Ergebnis einer Websuche im Chat-Kontext.
 *
 * Diese Klasse erweitert ToolResult, setzt standardmäßig den Namen
 * 'web_search' und erlaubt einfache Konstruktion aus Text/Texts.
 */
class WebSearchResult implements ChatMessage {
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
	 * @return list<array{type: 'tool_result', tool_id: string, content: mixed}>
	 */
	public function jsonSerialize(): array {
		return [[
			'type' => 'tool_result',
			'tool_id' => $this->id,
			'content' => $this->content,
		]];
	}
}
