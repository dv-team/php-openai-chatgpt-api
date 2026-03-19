<?php

namespace DvTeam\ChatGPT\Common;

use Stringable;

interface ChatModelName extends Stringable {
	public function __toString(): string;
	public function supportsTemperature(): bool;
	public function supportsTopP(): bool;
	public function supportsMaxTokens(): bool;
}
