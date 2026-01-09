<?php

namespace DvTeam\ChatGPT\PredefinedModels\TextToSpeech;

class GPTMiniTextToSpeech implements TextToSpeechModel {
	public function __toString(): string {
		return 'gpt-4o-mini-tts';
	}
}
