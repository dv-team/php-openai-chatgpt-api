<?php

namespace DvTeam\ChatGPT\PredefinedModels\TextToSpeech;

use Stringable;

interface TextToSpeechModel extends Stringable {
	public function __toString(): string;
}
