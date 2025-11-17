<?php

namespace DvTeam\ChatGPT\Common;

use Stringable;

interface ChatModelName extends Stringable {
	public function __toString(): string;
}