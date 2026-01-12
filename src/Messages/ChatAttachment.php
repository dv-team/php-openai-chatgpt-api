<?php

namespace DvTeam\ChatGPT\Messages;

interface ChatAttachment {
	/**
	 * Maps the attachment to the Responses API input schema message content items.
	 *
	 * @return object[]
	 */
	public function toInputContentParts(): array;
}
