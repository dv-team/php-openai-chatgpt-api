<?php

namespace DvTeam\ChatGPT\Common;

interface ContextSerializable {
	/**
	 * Serialize a message (or attachment) into simple structure data for transport/persistence.
	 *
	 * @return array<string, mixed>
	 */
	public function contextSerialize(): array;

	/**
	 * @param array<string, mixed>|object $data
	 */
	public static function contextUnserialize(array|object $data): self;
}
