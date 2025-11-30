<?php

namespace DvTeam\ChatGPT\MessageTypes;

/**
 * Spezielle Hilfsklasse für das Ergebnis einer Websuche im Chat-Kontext.
 *
 * Diese Klasse erweitert ToolResult, setzt standardmäßig den Namen
 * 'web_search' und erlaubt einfache Konstruktion aus Text/Texts.
 */
class WebSearchResult extends ToolResult {
	/**
	 * @param string $toolCallId ID des zugehörigen WebSearchCall
	 * @param string|array<string, mixed> $content
	 * @param string $name Werkzeugname (Standard: 'web_search')
	 */
	public function __construct(string $toolCallId, string|array $content, string $name = 'web_search') {
		parent::__construct(toolCallId: $toolCallId, name: $name, content: $content, role: 'tool');
	}

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
}

