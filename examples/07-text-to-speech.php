<?php

declare(strict_types = 1);

/** @var \DvTeam\ChatGPT\ChatGPT $chat */
$chat = require __DIR__ . '/_bootstrap.php';

$audio = $chat->textToSpeech(
	text: 'Hello from PHP!',
	voice: 'alloy',
	speed: 1.0,
	instructions: 'Sound calmly excited.',
	format: 'wav',
);

$out = sys_get_temp_dir() . '/openai-tts.wav';
file_put_contents($out, $audio);

echo "Wrote: {$out}\n";

