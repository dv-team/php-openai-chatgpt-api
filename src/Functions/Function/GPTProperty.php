<?php

namespace DvTeam\ChatGPT\Functions\Function;

use JsonSerializable;

interface GPTProperty extends JsonSerializable {
	public function getName(): string;
	public function isRequired(): bool;
}
