<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ChatMessage;

/**
 * Beschreibt ein Tool-Call-Result für den Message-Context, den das LLM ausführen lassen wollte.
 */
class ToolResult implements ChatMessage {
	/**
	 * @param string $toolCallId
	 * @param string $name
	 * @param null|scalar|array<string, mixed>|object $content
	 * @param string $role
	 */
	public function __construct(
		public string $toolCallId,
		public string $name,
		public mixed $content,
		public string $role = 'tool'
	) {}
}