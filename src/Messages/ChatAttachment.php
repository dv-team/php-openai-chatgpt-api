<?php

namespace DvTeam\ChatGPT\Messages;

interface ChatAttachment {
	/**
	 * Maps the attachment to the Responses API input schema message content items.
	 *
	 * If you want to serialize/transport a conversation context, implement {@see \DvTeam\ChatGPT\Common\ContextSerializable}
	 * on the attachment as well and provide a stable `type` in the serialized payload.
	 *
	 * @return object[]
	 */
	public function toInputContentParts(): array;
}
