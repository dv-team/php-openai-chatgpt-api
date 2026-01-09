<?php

namespace DvTeam\ChatGPT\Messages;

interface ChatAttachment {
	/**
	 * Maps the attachment to the Responses API input schema message content items.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function toInputContentParts(): array;
}
